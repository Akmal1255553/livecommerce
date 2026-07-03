<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

class RefundNotAllowedException extends BusinessException
{
    public function __construct(string $message = 'Refund is not allowed for this order.')
    {
        parent::__construct($message, 422, errorCode: ErrorCode::BusinessRule);
    }
}
