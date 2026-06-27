<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class UnauthorizedException extends BusinessException
{
    public function __construct(string $message = 'Unauthenticated.')
    {
        parent::__construct($message, 401, errorCode: ErrorCode::Unauthenticated);
    }
}
