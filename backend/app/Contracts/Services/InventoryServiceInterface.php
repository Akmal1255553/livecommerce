<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface InventoryServiceInterface
{
    public function assertAvailable(string $productId, ?int $variantId, int $quantity): void;

    /**
     * @param  list<array{product_id: string, variant_id: ?int, quantity: int}>  $lines
     */
    public function reserveForOrder(string $orderId, array $lines): string;

    public function confirmReservation(string $reservationGroupId): void;

    public function releaseReservation(string $reservationGroupId): void;
}
