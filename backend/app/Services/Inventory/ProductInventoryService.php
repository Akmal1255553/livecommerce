<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\InventoryServiceInterface;
use App\Enums\InventoryReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductInventoryService implements InventoryServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function assertAvailable(string $productId, ?int $variantId, int $quantity): void
    {
        $product = $this->products->findCatalogProduct($productId);

        if ($product === null) {
            throw new InsufficientStockException('Product is not available.');
        }

        $available = $this->availableQuantity($product, $variantId);

        if ($available < $quantity) {
            throw new InsufficientStockException('Insufficient stock for this product.');
        }
    }

    public function reserveForOrder(string $orderId, array $lines): string
    {
        return DB::transaction(function () use ($orderId, $lines): string {
            $groupId = (string) Str::uuid();
            $expiresAt = now()->addMinutes((int) config('commerce.inventory_reservation_ttl_minutes', 15));

            foreach ($lines as $line) {
                $productId = $line['product_id'];
                $variantId = $line['variant_id'] ?? null;
                $quantity = (int) $line['quantity'];

                $this->assertAvailable($productId, $variantId, $quantity);

                $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

                if ($variantId !== null) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($variantId)
                        ->lockForUpdate()
                        ->first();

                    if ($variant === null || $variant->stock_quantity < $quantity) {
                        throw new InsufficientStockException('Insufficient stock for this product.');
                    }

                    $variant->decrement('stock_quantity', $quantity);
                } else {
                    if ($product->stock_quantity < $quantity) {
                        throw new InsufficientStockException('Insufficient stock for this product.');
                    }

                    $product->decrement('stock_quantity', $quantity);
                }

                InventoryReservation::query()->create([
                    'reservation_group_id' => $groupId,
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'status' => InventoryReservationStatus::Active,
                    'expires_at' => $expiresAt,
                ]);
            }

            return $groupId;
        });
    }

    public function confirmReservation(string $reservationGroupId): void
    {
        InventoryReservation::query()
            ->where('reservation_group_id', $reservationGroupId)
            ->where('status', InventoryReservationStatus::Active)
            ->update(['status' => InventoryReservationStatus::Confirmed]);
    }

    public function releaseReservation(string $reservationGroupId): void
    {
        DB::transaction(function () use ($reservationGroupId): void {
            $reservations = InventoryReservation::query()
                ->where('reservation_group_id', $reservationGroupId)
                ->where('status', InventoryReservationStatus::Active)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->restoreStock($reservation);
                $reservation->status = InventoryReservationStatus::Released;
                $reservation->save();
            }
        });
    }

    public function releaseExpiredReservations(): int
    {
        $groupIds = InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active)
            ->where('expires_at', '<', now())
            ->distinct()
            ->pluck('reservation_group_id');

        $released = 0;

        foreach ($groupIds as $groupId) {
            $this->releaseReservation((string) $groupId);
            $released++;
        }

        return $released;
    }

    private function restoreStock(InventoryReservation $reservation): void
    {
        if ($reservation->variant_id !== null) {
            ProductVariant::query()
                ->whereKey($reservation->variant_id)
                ->increment('stock_quantity', $reservation->quantity);

            return;
        }

        Product::query()
            ->whereKey($reservation->product_id)
            ->increment('stock_quantity', $reservation->quantity);
    }

    private function availableQuantity(Product $product, ?int $variantId): int
    {
        if ($variantId !== null) {
            $variant = $product->variants->firstWhere('id', $variantId);

            return $variant !== null ? $variant->stock_quantity : 0;
        }

        return $product->stock_quantity;
    }
}
