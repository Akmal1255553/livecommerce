<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionType: string
{
    case TopUp = 'topup';
    case Withdrawal = 'withdrawal';
    case OrderPayment = 'order_payment';
    case Refund = 'refund';
    case Payout = 'payout';
    case Adjustment = 'adjustment';

    /** Credits increase the balance; everything else debits it. */
    public function isCredit(): bool
    {
        return match ($this) {
            self::TopUp, self::Refund, self::Payout => true,
            self::Withdrawal, self::OrderPayment => false,
            self::Adjustment => true,
        };
    }
}
