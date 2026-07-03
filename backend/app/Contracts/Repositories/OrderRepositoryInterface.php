<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function createWithItems(Order $order, array $items, OrderStatusTransition $initialTransition): Order;

    public function saveOrder(Order $order, int $expectedVersion): Order;

    public function findByIdForBuyer(string $orderId, string $userId): ?Order;

    public function findByIdForStore(string $orderId, string $storeId): ?Order;

    public function findByIdempotencyTransition(string $orderId, string $idempotencyKey): ?OrderStatusTransition;

    public function appendTransition(OrderStatusTransition $transition): OrderStatusTransition;

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForBuyer(string $userId, array $filters, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForStore(string $storeId, array $filters, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return list<Order>
     */
    public function findDeliveredReadyForCompletion(\DateTimeInterface $before): array;
}
