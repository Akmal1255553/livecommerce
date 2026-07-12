<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\DTOs\Store\SellerAnalyticsSummaryData;
use App\DTOs\Store\SellerDashboardData;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreService extends BaseService implements StoreServiceInterface
{
    private const LOW_STOCK_THRESHOLD = 5;

    /** @var list<OrderStatus> */
    private const REVENUE_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Packing,
        OrderStatus::ReadyToShip,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
        OrderStatus::Completed,
    ];

    /** @var list<OrderStatus> */
    private const PENDING_STATUSES = [
        OrderStatus::AwaitingPayment,
        OrderStatus::Paid,
        OrderStatus::Packing,
        OrderStatus::ReadyToShip,
        OrderStatus::Shipped,
    ];

    public function __construct(
        StructuredLogger $logger,
        private readonly StoreRepositoryInterface $stores,
        private readonly ProductRepositoryInterface $products,
    ) {
        parent::__construct($logger);
    }

    public function apply(User $user, array $payload): Store
    {
        if ($this->stores->findByUserId((string) $user->id) !== null) {
            throw new ConflictException('Seller store already exists for this user.');
        }

        $name = trim($payload['store_name']);
        $slug = $this->uniqueSlug(
            isset($payload['slug']) && is_string($payload['slug']) && $payload['slug'] !== ''
                ? $payload['slug']
                : $name,
        );

        return DB::transaction(function () use ($user, $payload, $name, $slug): Store {
            if (! $user->hasRole(UserRole::Seller)) {
                $user->forceFill(['role' => UserRole::Seller])->save();
            }

            $store = $this->stores->create([
                'user_id' => $user->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? null,
                'status' => StoreStatus::Active,
                'commission_rate' => 10.00,
            ]);

            $this->logger->info('store.seller_applied', [
                'user_id' => $user->id,
                'store_id' => $store->id,
                'slug' => $store->slug,
            ]);

            return $store->fresh() ?? $store;
        });
    }

    public function getBySlug(string $slug): Store
    {
        $store = $this->stores->findActiveBySlug($slug);

        if ($store === null) {
            throw new ResourceNotFoundException('Store not found.');
        }

        return $store;
    }

    public function dashboard(Store $store): SellerDashboardData
    {
        $productQuery = Product::query()->where('store_id', $store->id);
        $orderQuery = Order::query()->where('store_id', $store->id);

        $totalProducts = (clone $productQuery)->count();
        $activeProducts = (clone $productQuery)->where('status', ProductStatus::Active)->count();
        $lowStockProducts = (clone $productQuery)
            ->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD)
            ->where('stock_quantity', '>', 0)
            ->count();

        $totalOrders = (clone $orderQuery)->count();
        $pendingOrders = (clone $orderQuery)
            ->whereIn('status', array_map(static fn (OrderStatus $s) => $s->value, self::PENDING_STATUSES))
            ->count();

        $totalRevenue = (int) (clone $orderQuery)
            ->whereIn('status', array_map(static fn (OrderStatus $s) => $s->value, self::REVENUE_STATUSES))
            ->sum('total');

        return new SellerDashboardData(
            storeId: (string) $store->id,
            storeName: $store->name,
            storeSlug: $store->slug,
            storeStatus: $store->status->value,
            totalProducts: $totalProducts,
            activeProducts: $activeProducts,
            lowStockProducts: $lowStockProducts,
            pendingOrders: $pendingOrders,
            totalOrders: $totalOrders,
            totalRevenue: $totalRevenue,
            currency: 'UZS',
        );
    }

    public function analyticsSummary(Store $store, string $period = 'last_30_days'): SellerAnalyticsSummaryData
    {
        $days = match ($period) {
            'last_7_days' => 7,
            'last_90_days' => 90,
            default => 30,
        };
        $normalizedPeriod = match ($period) {
            'last_7_days', 'last_90_days' => $period,
            default => 'last_30_days',
        };

        $from = now()->subDays($days)->startOfDay();

        $orders = Order::query()
            ->where('store_id', $store->id)
            ->where('created_at', '>=', $from);

        $totalOrders = (clone $orders)->count();
        $pendingOrders = (clone $orders)
            ->whereIn('status', array_map(static fn (OrderStatus $s) => $s->value, self::PENDING_STATUSES))
            ->count();
        $totalRevenue = (int) (clone $orders)
            ->whereIn('status', array_map(static fn (OrderStatus $s) => $s->value, self::REVENUE_STATUSES))
            ->sum('total');

        $dayExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'date(created_at)'
            : 'DATE(created_at)';

        $revenueStatuses = collect(self::REVENUE_STATUSES)
            ->map(static fn (OrderStatus $s) => "'{$s->value}'")
            ->implode(',');

        $seriesRows = Order::query()
            ->selectRaw("{$dayExpression} as day, COUNT(*) as orders_count, COALESCE(SUM(CASE WHEN status IN ({$revenueStatuses}) THEN total ELSE 0 END), 0) as revenue")
            ->where('store_id', $store->id)
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $series = $seriesRows->map(static fn ($row): array => [
            'date' => (string) $row->day,
            'orders' => (int) $row->orders_count,
            'revenue' => (int) $row->revenue,
        ])->values()->all();

        $totalProducts = Product::query()->where('store_id', $store->id)->count();

        return new SellerAnalyticsSummaryData(
            totalRevenue: $totalRevenue,
            totalOrders: $totalOrders,
            pendingOrders: $pendingOrders,
            totalProducts: $totalProducts,
            period: $normalizedPeriod,
            currency: 'UZS',
            series: $series,
        );
    }

    public function listPublicProducts(Store $store, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->products->paginateByStore($store, $page, $perPage, sellerView: false);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'store';
        }

        $slug = $base;
        $suffix = 1;

        while ($this->stores->findBySlug($slug) !== null) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
