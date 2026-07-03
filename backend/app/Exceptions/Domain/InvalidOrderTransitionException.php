<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class InvalidOrderTransitionException extends BusinessException
{
    public function __construct(string $message = 'Invalid order status transition.')
    {
        parent::__construct($message, 409, errorCode: ErrorCode::Conflict);
    }
}
