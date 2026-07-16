<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Services\InventoryServiceInterface;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;

class ReleaseInventoryOnOrderCancelled
{
    public function __construct(
        private readonly InventoryServiceInterface $inventory,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        if ($event->fromStatus !== OrderStatus::AwaitingPayment
            && $event->fromStatus !== OrderStatus::Pending) {
            return;
        }

        $this->inventory->releaseReservationForOrder($event->order->id);
    }
}
