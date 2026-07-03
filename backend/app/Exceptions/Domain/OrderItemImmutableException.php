<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class OrderItemImmutableException extends BusinessException
{
    public function __construct(string $message = 'Order line items cannot be modified after creation.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::BusinessRule);
    }
}
