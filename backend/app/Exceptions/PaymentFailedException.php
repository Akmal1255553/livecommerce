<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

class PaymentFailedException extends BusinessException
{
    public function __construct(string $message = 'Payment processing failed.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::PaymentFailed);
    }
}
