<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use RuntimeException;

class VideoProcessingException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
