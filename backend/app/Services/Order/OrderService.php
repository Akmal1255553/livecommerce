<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Services\OrderNumberGeneratorInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\OrderStateMachineInterface;
use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\PaymentSnapshot;
use App\DTOs\Order\ShipmentSnapshot;
use App\DTOs\Order\TransitionContext;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderPackingStarted;
use App\Events\OrderPaid;
use App\Events\OrderReadyToShip;
use App\Events\OrderShipped;
use App\Events\RefundApproved;
use App\Events\RefundCompleted;
use App\Events\RefundRejected;
use App\Events\RefundRequested;
use App\Exceptions\Domain\OrderConcurrentModificationException;
use App\Exceptions\Domain\OrderNotCancellableException;
use App\Exceptions\Domain\RefundNotAllowedException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\RefundRequest;
use App\Services\BaseService;
use App\ValueObjects\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService extends BaseService implements OrderServiceInterface
{
    public function __construct(
        StructuredLogger $logger,
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderStateMachineInterface $stateMachine,
        private readonly OrderNumberGeneratorInterface $orderNumbers,
    ) {
        parent::__construct($logger);
    }

    public function createFromCheckout(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $orderNumber = $this->orderNumbers->generate();

            $order = new Order([
                'order_number' => $orderNumber,
                'user_id' => $data->userId,
                'store_id' => $data->storeId,
                'status' => OrderStatus::Draft,
                'version' => 1,
                'notes' => $data->notes,
                ...$data->totals->toOrderAttributes(),
                ...$data->payment->toOrderAttributes(),
                ...$data->shipment->toOrderAttributes(),
            ]);

            $items = array_map(
                static fn ($line) => $line->toItemAttributes(''),
                $data->lines,
            );

            $initialTransition = new OrderStatusTransition([
                'from_status' => OrderStatus::Draft,
                'to_status' => OrderStatus::Draft,
                'actor_type' => OrderActor::System,
                'actor_id' => null,
                'metadata' => ['event' => 'order_created'],
            ]);

            $order = $this->orders->createWithItems($order, $items, $initialTransition);

            $order = $this->transition(
                $order,
                OrderStatus::Pending,
                new TransitionContext(
                    actor: OrderActor::System,
                    metadata: ['event' => 'checkout_complete'],
                ),
            );

            OrderCreated::dispatch($order);

            return $order->fresh(['items.product', 'transitions', 'store', 'activeRefund']);
        });
    }

    public function transition(Order $order, OrderStatus $to, TransitionContext $ctx): Order
    {
        return DB::transaction(function () use ($order, $to, $ctx): Order {
            $order = $order->fresh(['items.product', 'transitions', 'store', 'activeRefund']);

            if ($order === null) {
                throw new ResourceNotFoundException('Order not found.');
            }

            if ($ctx->idempotencyKey !== null) {
                $existing = $this->orders->findByIdempotencyTransition($order->id, $ctx->idempotencyKey);

                if ($existing !== null && $existing->to_status === $to) {
                    return $order;
                }
            }

            if ($ctx->expectedVersion !== null && $order->version !== $ctx->expectedVersion) {
                throw new OrderConcurrentModificationException;
            }

            $from = $order->status;

            if ($from !== $to) {
                $this->stateMachine->assertCanTransition($from, $to, $ctx->actor);
            }

            $expectedVersion = $order->version;

            if ($from !== $to) {
                $order->status = $to;
            }

            $this->applyStatusSideEffects($order, $from, $to, $ctx);

            if ($ctx->shipmentPatch !== null) {
                $this->applyShipmentPatch($order, $ctx->shipmentPatch);
            }

            if ($ctx->paymentPatch !== null) {
                $this->applyPaymentPatch($order, $ctx->paymentPatch);
            }

            if ($from !== $to) {
                $transition = new OrderStatusTransition([
                    'order_id' => $order->id,
                    'from_status' => $from,
                    'to_status' => $to,
                    'actor_type' => $ctx->actor,
                    'actor_id' => $ctx->actorId,
                    'reason' => $ctx->reason,
                    'idempotency_key' => $ctx->idempotencyKey,
                    'metadata' => $ctx->metadata,
                ]);

                $this->orders->appendTransition($transition);
            }

            $order = $this->orders->saveOrder($order, $expectedVersion);

            if ($from !== $to) {
                $this->dispatchStatusEvent($order, $from, $to);
            }

            if ($to === OrderStatus::RefundRejected && $order->status_before_refund !== null) {
                $restoreTo = OrderStatus::from($order->status_before_refund);

                return $this->transition(
                    $order,
                    $restoreTo,
                    new TransitionContext(
                        actor: OrderActor::System,
                        metadata: ['restored_from' => OrderStatus::RefundRejected->value],
                    ),
                );
            }

            return $order;
        });
    }

    public function getForBuyer(string $userId, string $orderId): Order
    {
        $order = $this->orders->findByIdForBuyer($orderId, $userId);

        if ($order === null) {
            throw new ResourceNotFoundException('Order not found.');
        }

        return $order;
    }

    public function getForSeller(string $storeId, string $orderId): Order
    {
        $order = $this->orders->findByIdForStore($orderId, $storeId);

        if ($order === null) {
            throw new ResourceNotFoundException('Order not found.');
        }

        return $order;
    }

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function listForBuyer(string $userId, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateForBuyer($userId, $filters, $page, $perPage);
    }

    /**
     * @param  array{status?: OrderStatus}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function listForSeller(string $storeId, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateForStore($storeId, $filters, $page, $perPage);
    }

    public function cancelForBuyer(
        string $userId,
        string $orderId,
        ?string $idempotencyKey = null,
        ?string $reason = null,
    ): Order {
        $order = $this->getForBuyer($userId, $orderId);

        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        if ($idempotencyKey !== null) {
            $existing = $this->orders->findByIdempotencyTransition($orderId, $idempotencyKey);

            if ($existing !== null && $existing->to_status === OrderStatus::Cancelled) {
                return $order;
            }
        }

        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], true)) {
            throw new OrderNotCancellableException;
        }

        return $this->transition(
            $order,
            OrderStatus::Cancelled,
            new TransitionContext(
                actor: OrderActor::Buyer,
                actorId: $userId,
                idempotencyKey: $idempotencyKey,
                reason: $reason,
            ),
        );
    }

    public function requestRefund(
        string $userId,
        string $orderId,
        string $reason,
        ?string $idempotencyKey = null,
    ): Order {
        $order = $this->getForBuyer($userId, $orderId);

        if ($order->status === OrderStatus::RefundRequested && $idempotencyKey !== null) {
            $existing = $this->orders->findByIdempotencyTransition($order->id, $idempotencyKey);

            if ($existing !== null) {
                return $order;
            }
        }

        $this->assertRefundAllowed($order);

        if ($order->status === OrderStatus::RefundRequested) {
            throw new RefundNotAllowedException('Refund already requested.');
        }

        return DB::transaction(function () use ($order, $userId, $reason, $idempotencyKey): Order {
            $refund = RefundRequest::query()->create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'reason' => $reason,
                'status' => RefundRequestStatus::Requested,
            ]);

            $order->status_before_refund = $order->status->value;
            $order->active_refund_id = $refund->id;
            $order->save();

            return $this->transition(
                $order->fresh(),
                OrderStatus::RefundRequested,
                new TransitionContext(
                    actor: OrderActor::Buyer,
                    actorId: $userId,
                    idempotencyKey: $idempotencyKey,
                    reason: $reason,
                    metadata: ['refund_id' => $refund->id],
                ),
            );
        });
    }

    public function resolveRefund(
        string $storeId,
        string $refundId,
        bool $approve,
        ?string $idempotencyKey = null,
    ): Order {
        $refund = RefundRequest::query()
            ->with('order')
            ->whereKey($refundId)
            ->first();

        if ($refund === null || ! $refund->order instanceof Order || $refund->order->store_id !== $storeId) {
            throw new ResourceNotFoundException('Refund request not found.');
        }

        $order = $refund->order;

        if ($order->status !== OrderStatus::RefundRequested) {
            throw new RefundNotAllowedException('Refund is not awaiting resolution.');
        }

        $target = $approve ? OrderStatus::RefundApproved : OrderStatus::RefundRejected;

        if ($idempotencyKey !== null) {
            $existing = $this->orders->findByIdempotencyTransition($order->id, $idempotencyKey);

            if ($existing !== null && $existing->to_status === $target) {
                return $this->getForSeller($storeId, $order->id);
            }
        }

        return DB::transaction(function () use ($refund, $order, $approve, $target, $idempotencyKey, $storeId): Order {
            $refund->status = $approve ? RefundRequestStatus::Approved : RefundRequestStatus::Rejected;
            $refund->save();

            $order = $this->transition(
                $order->fresh(),
                $target,
                new TransitionContext(
                    actor: OrderActor::Seller,
                    actorId: $storeId,
                    idempotencyKey: $idempotencyKey,
                    metadata: ['refund_id' => $refund->id],
                ),
            );

            if ($approve) {
                $refund->status = RefundRequestStatus::Processing;
                $refund->save();

                $order = $this->transition(
                    $order,
                    OrderStatus::Refunded,
                    new TransitionContext(
                        actor: OrderActor::System,
                        idempotencyKey: $idempotencyKey !== null ? $idempotencyKey.'-refunded' : null,
                        metadata: ['refund_id' => $refund->id],
                    ),
                );

                $refund->status = RefundRequestStatus::Completed;
                $refund->save();

                return $this->getForSeller($storeId, $order->id);
            }

            return $this->getForSeller($storeId, $order->id);
        });
    }

    public function updateStatusForSeller(
        string $storeId,
        string $orderId,
        OrderStatus $status,
        ?string $trackingNumber = null,
        ?string $carrier = null,
        ?int $expectedVersion = null,
        ?string $idempotencyKey = null,
    ): Order {
        $order = $this->getForSeller($storeId, $orderId);

        if ($status === OrderStatus::Shipped && ($trackingNumber === null || $trackingNumber === '')) {
            throw ValidationException::withMessages([
                'tracking_number' => ['Tracking number is required when marking order as shipped.'],
            ]);
        }

        $shipmentPatch = null;

        if ($trackingNumber !== null || $carrier !== null) {
            $shipmentPatch = new ShipmentSnapshot(
                address: $order->shipping_address,
                carrier: $carrier ?? $order->carrier,
                trackingNumber: $trackingNumber ?? $order->tracking_number,
            );
        }

        return $this->transition(
            $order,
            $status,
            new TransitionContext(
                actor: OrderActor::Seller,
                actorId: $storeId,
                idempotencyKey: $idempotencyKey,
                expectedVersion: $expectedVersion,
                shipmentPatch: $shipmentPatch,
            ),
        );
    }

    public function markAwaitingPayment(Order $order): Order
    {
        return $this->transition(
            $order,
            OrderStatus::AwaitingPayment,
            new TransitionContext(actor: OrderActor::System),
        );
    }

    public function markPaid(Order $order, ?string $transactionId = null): Order
    {
        return $this->transition(
            $order,
            OrderStatus::Paid,
            new TransitionContext(
                actor: OrderActor::System,
                paymentPatch: new PaymentSnapshot(
                    provider: $order->payment_provider ?? 'fake',
                    method: $order->payment_method,
                    transactionId: $transactionId,
                    amount: Money::uzs($order->total),
                    status: PaymentStatus::Paid->value,
                    paidAt: now(),
                ),
            ),
        );
    }

    private function assertRefundAllowed(Order $order): void
    {
        $allowedStatuses = [OrderStatus::Paid, OrderStatus::Delivered, OrderStatus::Completed];

        if (! in_array($order->status, $allowedStatuses, true)) {
            throw new RefundNotAllowedException;
        }

        $windowDays = (int) config('commerce.refund_window_days', 14);
        $reference = $order->delivered_at ?? $order->completed_at ?? $order->paid_at ?? $order->created_at;

        if ($reference !== null && $reference->addDays($windowDays)->isPast()) {
            throw new RefundNotAllowedException('Refund window has expired.');
        }
    }

    private function applyStatusSideEffects(
        Order $order,
        OrderStatus $from,
        OrderStatus $to,
        TransitionContext $ctx,
    ): void {
        match ($to) {
            OrderStatus::Cancelled => $order->cancelled_at = now(),
            OrderStatus::Paid => $order->paid_at = now(),
            OrderStatus::Shipped => $order->shipped_at = now(),
            OrderStatus::Delivered => $order->delivered_at = now(),
            OrderStatus::Completed => $order->completed_at = now(),
            OrderStatus::Refunded => $order->payment_status = PaymentStatus::Refunded,
            default => null,
        };

        if ($to === OrderStatus::Paid) {
            $order->payment_status = PaymentStatus::Paid;
        }
    }

    private function applyShipmentPatch(Order $order, ShipmentSnapshot $patch): void
    {
        $attributes = $patch->toOrderAttributes();

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $order->{$key} = $value;
            }
        }
    }

    private function applyPaymentPatch(Order $order, PaymentSnapshot $patch): void
    {
        $attributes = $patch->toOrderAttributes();

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $order->{$key} = $value;
            }
        }
    }

    private function dispatchStatusEvent(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        match ($to) {
            OrderStatus::AwaitingPayment => OrderConfirmed::dispatch($order),
            OrderStatus::Cancelled => OrderCancelled::dispatch($order, $from),
            OrderStatus::Paid => OrderPaid::dispatch($order),
            OrderStatus::Packing => OrderPackingStarted::dispatch($order),
            OrderStatus::ReadyToShip => OrderReadyToShip::dispatch($order),
            OrderStatus::Shipped => OrderShipped::dispatch($order),
            OrderStatus::Delivered => OrderDelivered::dispatch($order),
            OrderStatus::Completed => OrderCompleted::dispatch($order),
            OrderStatus::RefundRequested => RefundRequested::dispatch($order),
            OrderStatus::RefundApproved => RefundApproved::dispatch($order),
            OrderStatus::RefundRejected => RefundRejected::dispatch($order),
            OrderStatus::Refunded => RefundCompleted::dispatch($order),
            default => null,
        };
    }
}
