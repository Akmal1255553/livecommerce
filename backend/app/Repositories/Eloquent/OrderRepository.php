<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Enums\OrderStatus;
use App\Exceptions\Domain\OrderConcurrentModificationException;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseEloquentRepository<Order>
 */
class OrderRepository extends BaseEloquentRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function createWithItems(Order $order, array $items, OrderStatusTransition $initialTransition): Order
    {
        $order->save();

        foreach ($items as $item) {
            unset($item['order_id']);
            $order->items()->create($item);
        }

        $initialTransition->order_id = $order->id;
        $initialTransition->save();

        return $order->load(['items.product', 'transitions', 'store']);
    }

    public function saveOrder(Order $order, int $expectedVersion): Order
    {
        $attributes = collect($order->getAttributes())
            ->except(['id', 'created_at'])
            ->merge([
                'version' => $expectedVersion + 1,
                'updated_at' => now(),
            ])
            ->all();

        $updated = Order::query()
            ->whereKey($order->id)
            ->where('version', $expectedVersion)
            ->update($attributes);

        if ($updated === 0) {
            throw new OrderConcurrentModificationException;
        }

        return $order->refresh()->load(['items.product', 'transitions', 'store', 'activeRefund']);
    }

    public function findByIdForBuyer(string $orderId, string $userId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('user_id', $userId)
            ->with(['items.product', 'transitions', 'store', 'activeRefund'])
            ->first();
    }

    public function findByIdForStore(string $orderId, string $storeId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('store_id', $storeId)
            ->with(['items.product', 'transitions', 'store', 'activeRefund'])
            ->first();
    }

    public function findByIdempotencyTransition(string $orderId, string $idempotencyKey): ?OrderStatusTransition
    {
        return OrderStatusTransition::query()
            ->where('order_id', $orderId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function appendTransition(OrderStatusTransition $transition): OrderStatusTransition
    {
        $transition->save();

        return $transition;
    }

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForBuyer(string $userId, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->with(['items.product', 'store'])
            ->orderByDesc('created_at');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForStore(string $storeId, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('store_id', $storeId)
            ->with(['items.product', 'buyer'])
            ->orderByDesc('created_at');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function findDeliveredReadyForCompletion(\DateTimeInterface $before): array
    {
        return Order::query()
            ->where('status', OrderStatus::Delivered)
            ->where('delivered_at', '<=', $before)
            ->get()
            ->all();
    }
}
