<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class ResourceNotFoundException extends BusinessException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message, 404, errorCode: ErrorCode::NotFound);
    }
}
