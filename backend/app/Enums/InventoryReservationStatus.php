<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Confirmed = 'confirmed';
    case Released = 'released';
}
