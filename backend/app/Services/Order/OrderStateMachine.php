<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Services\OrderStateMachineInterface;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidOrderTransitionException;

final class OrderStateMachine implements OrderStateMachineInterface
{
    /**
     * @var array<string, list<OrderStatus>>
     */
    private const TRANSITIONS = [
        'draft:system' => [OrderStatus::Pending],
        'pending:system' => [OrderStatus::AwaitingPayment, OrderStatus::Cancelled],
        'pending:buyer' => [OrderStatus::Cancelled],
        'awaiting_payment:system' => [OrderStatus::Paid, OrderStatus::Cancelled],
        'awaiting_payment:buyer' => [OrderStatus::Cancelled],
        'awaiting_payment:payment_gateway' => [OrderStatus::Paid, OrderStatus::Cancelled],
        'paid:seller' => [OrderStatus::Packing],
        'paid:buyer' => [OrderStatus::RefundRequested],
        'packing:seller' => [OrderStatus::ReadyToShip],
        'ready_to_ship:seller' => [OrderStatus::Shipped],
        'shipped:seller' => [OrderStatus::Delivered],
        'shipped:buyer' => [OrderStatus::Delivered],
        'shipped:system' => [OrderStatus::Delivered],
        'delivered:system' => [OrderStatus::Completed],
        'delivered:buyer' => [OrderStatus::Completed, OrderStatus::RefundRequested],
        'completed:buyer' => [OrderStatus::RefundRequested],
        'refund_requested:seller' => [OrderStatus::RefundApproved, OrderStatus::RefundRejected],
        'refund_requested:admin' => [OrderStatus::RefundApproved, OrderStatus::RefundRejected],
        'refund_approved:system' => [OrderStatus::Refunded],
        'refund_approved:payment_gateway' => [OrderStatus::Refunded],
        'refund_rejected:system' => [OrderStatus::Paid, OrderStatus::Delivered, OrderStatus::Completed],
    ];

    public function assertCanTransition(
        OrderStatus $from,
        OrderStatus $to,
        OrderActor $actor,
    ): void {
        if ($from === $to) {
            return;
        }

        $allowed = $this->allowedTargets($from, $actor);

        if (! in_array($to, $allowed, true)) {
            throw new InvalidOrderTransitionException(
                "Invalid order status transition: {$from->value} → {$to->value} for actor {$actor->value}."
            );
        }
    }

    public function allowedTargets(OrderStatus $from, OrderActor $actor): array
    {
        $key = "{$from->value}:{$actor->value}";

        return self::TRANSITIONS[$key] ?? [];
    }
}
