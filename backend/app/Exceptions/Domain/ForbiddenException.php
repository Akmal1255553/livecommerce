<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class ForbiddenException extends BusinessException
{
    public function __construct(string $message = 'Forbidden.')
    {
        parent::__construct($message, 403, errorCode: ErrorCode::Forbidden);
    }
}
