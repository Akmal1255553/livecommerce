<?php

declare(strict_types=1);

namespace App\Enums;

/** Transaction states defined by the Payme Merchant API. */
enum PaymeTransactionState: int
{
    case Created = 1;
    case Performed = 2;
    case CancelledBeforePerform = -1;
    case CancelledAfterPerform = -2;

    public function isCancelled(): bool
    {
        return $this === self::CancelledBeforePerform || $this === self::CancelledAfterPerform;
    }
}
