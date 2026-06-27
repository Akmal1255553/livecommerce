<?php

declare(strict_types=1);

namespace App\Enums;

enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case ValidationFailed = 'VALIDATION_FAILED';
    case Conflict = 'CONFLICT';
    case RateLimited = 'RATE_LIMITED';
    case BusinessRule = 'BUSINESS_RULE';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case PaymentFailed = 'PAYMENT_FAILED';
    case ServerError = 'SERVER_ERROR';
}
