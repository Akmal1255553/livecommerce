<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class CartStaleException extends BusinessException
{
    public function __construct(string $message = 'Cart changed — refresh and try again.')
    {
        parent::__construct($message, 409, errorCode: ErrorCode::CartStale);
    }
}
