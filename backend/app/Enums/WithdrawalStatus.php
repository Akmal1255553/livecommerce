<?php

declare(strict_types=1);

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Funds stay held until the request leaves these states. */
    public function holdsFunds(): bool
    {
        return $this === self::Requested || $this === self::Processing;
    }
}
