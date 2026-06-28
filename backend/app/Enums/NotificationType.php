<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case NEW_FOLLOWER = 'NEW_FOLLOWER';
    case NEW_COMMENT = 'NEW_COMMENT';
    case NEW_LIKE = 'NEW_LIKE';
    case LIVE_STARTED = 'LIVE_STARTED';
    case ORDER_CREATED = 'ORDER_CREATED';
    case ORDER_PAID = 'ORDER_PAID';
    case REFUND_APPROVED = 'REFUND_APPROVED';
}
