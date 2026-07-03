<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Services\MetricsServiceInterface;
use App\Enums\EngagementEventType;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\RefundCompleted;
use App\Events\RefundRequested;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

class RecordOrderAnalytics
{
    public function __construct(
        private readonly MetricsServiceInterface $metrics,
    ) {}

    public function handleOrderCreated(OrderCreated $event): void
    {
        $this->record($this->resolveUser($event->order), EngagementEventType::OrderCreated, [
            'order_id' => $event->order->id,
            'store_id' => $event->order->store_id,
            'total' => $event->order->total,
            'currency' => $event->order->currency,
            'item_count' => $event->order->items->count(),
        ]);
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        $this->record($this->resolveUser($event->order), EngagementEventType::OrderPaid, [
            'order_id' => $event->order->id,
            'payment_provider' => $event->order->payment_provider,
            'amount' => $event->order->total,
        ]);
    }

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        $this->record($this->resolveUser($event->order), EngagementEventType::OrderCancelled, [
            'order_id' => $event->order->id,
            'status_before' => $event->fromStatus->value,
        ]);
    }

    public function handleRefundRequested(RefundRequested $event): void
    {
        $this->record($this->resolveUser($event->order), EngagementEventType::RefundRequested, [
            'order_id' => $event->order->id,
            'refund_id' => $event->order->active_refund_id,
        ]);
    }

    public function handleRefundCompleted(RefundCompleted $event): void
    {
        $this->record($this->resolveUser($event->order), EngagementEventType::RefundCompleted, [
            'order_id' => $event->order->id,
            'refund_id' => $event->order->active_refund_id,
            'amount' => $event->order->total,
        ]);
    }

    private function resolveUser(Order $order): ?User
    {
        if ($order->relationLoaded('buyer')) {
            return $order->buyer;
        }

        return User::query()->whereKey($order->user_id)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(?User $user, EngagementEventType $type, array $payload): void
    {
        $this->metrics->record(
            $user,
            $type,
            (string) Str::uuid(),
            null,
            $payload,
        );
    }
}
