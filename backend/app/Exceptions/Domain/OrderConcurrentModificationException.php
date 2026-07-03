<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class OrderConcurrentModificationException extends BusinessException
{
    public function __construct(string $message = 'Order was modified by another request. Refresh and try again.')
    {
        parent::__construct($message, 409, errorCode: ErrorCode::Conflict);
    }
}
