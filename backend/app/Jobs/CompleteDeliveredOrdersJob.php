<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\DTOs\Order\TransitionContext;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompleteDeliveredOrdersJob implements ShouldQueue
{
    use Queueable;

    public function handle(OrderServiceInterface $orders): void
    {
        $days = (int) config('commerce.auto_complete_delivered_days', 7);
        $before = now()->subDays($days);

        $candidates = app(OrderRepositoryInterface::class)
            ->findDeliveredReadyForCompletion($before);

        foreach ($candidates as $order) {
            if ($order->status !== OrderStatus::Delivered) {
                continue;
            }

            $orders->transition(
                $order,
                OrderStatus::Completed,
                new TransitionContext(actor: OrderActor::System),
            );
        }
    }
}
