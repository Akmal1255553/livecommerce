<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\InventoryServiceInterface;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use LogicException;

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
        throw new LogicException('Inventory reservation is implemented in Sprint 4.5.');
    }

    public function confirmReservation(string $reservationGroupId): void
    {
        throw new LogicException('Inventory reservation is implemented in Sprint 4.5.');
    }

    public function releaseReservation(string $reservationGroupId): void
    {
        throw new LogicException('Inventory reservation is implemented in Sprint 4.5.');
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
