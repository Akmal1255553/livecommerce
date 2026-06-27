<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

class InsufficientStockException extends BusinessException
{
    public function __construct(string $message = 'Insufficient stock for this product.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::InsufficientStock);
    }
}
