<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;
use Throwable;

class BusinessException extends Exception
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        string $message,
        protected int $statusCode = 400,
        protected ?array $errors = null,
        protected ErrorCode $errorCode = ErrorCode::BusinessRule,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
