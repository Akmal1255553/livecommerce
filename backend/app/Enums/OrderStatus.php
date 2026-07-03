<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Packing = 'packing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case RefundRequested = 'refund_requested';
    case RefundApproved = 'refund_approved';
    case RefundRejected = 'refund_rejected';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Refunded], true);
    }
}
