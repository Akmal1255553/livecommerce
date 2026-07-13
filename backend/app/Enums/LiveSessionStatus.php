<?php

declare(strict_types=1);

namespace App\Enums;

enum LiveSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
