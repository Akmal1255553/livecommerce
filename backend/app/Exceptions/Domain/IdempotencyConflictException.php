<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class IdempotencyConflictException extends BusinessException
{
    public function __construct(string $message = 'Idempotency key was already used with a different request.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::Conflict);
    }
}
