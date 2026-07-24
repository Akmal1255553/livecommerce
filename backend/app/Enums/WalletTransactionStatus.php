<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
