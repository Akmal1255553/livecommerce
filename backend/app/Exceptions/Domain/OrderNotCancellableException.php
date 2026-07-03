<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class OrderNotCancellableException extends BusinessException
{
    public function __construct(string $message = 'Order cannot be cancelled in its current status.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::BusinessRule);
    }
}
