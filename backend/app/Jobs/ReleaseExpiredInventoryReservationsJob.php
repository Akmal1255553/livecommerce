<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\InventoryServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredInventoryReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(InventoryServiceInterface $inventory): void
    {
        $inventory->releaseExpiredReservations();
    }
}
