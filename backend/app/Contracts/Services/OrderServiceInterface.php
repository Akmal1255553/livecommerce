<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\TransitionContext;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    public function createFromCheckout(CreateOrderData $data): Order;

    public function transition(Order $order, OrderStatus $to, TransitionContext $ctx): Order;

    public function getForBuyer(string $userId, string $orderId): Order;

    public function getForSeller(string $storeId, string $orderId): Order;

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function listForBuyer(string $userId, array $filters, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function listForSeller(string $storeId, array $filters, int $page, int $perPage): LengthAwarePaginator;

    public function cancelForBuyer(
        string $userId,
        string $orderId,
        ?string $idempotencyKey = null,
        ?string $reason = null,
    ): Order;

    public function requestRefund(
        string $userId,
        string $orderId,
        string $reason,
        ?string $idempotencyKey = null,
    ): Order;

    public function resolveRefund(
        string $storeId,
        string $refundId,
        bool $approve,
        ?string $idempotencyKey = null,
    ): Order;

    public function markAwaitingPayment(Order $order): Order;

    public function markPaid(Order $order, ?string $transactionId = null): Order;

    public function updateStatusForSeller(
        string $storeId,
        string $orderId,
        OrderStatus $status,
        ?string $trackingNumber = null,
        ?string $carrier = null,
        ?int $expectedVersion = null,
        ?string $idempotencyKey = null,
    ): Order;
}
