<?php

declare(strict_types=1);

namespace App\DTOs\Health;

use App\DTOs\DataTransferObject;

readonly class HealthStatusData extends DataTransferObject
{
    public function __construct(
        public string $status,
        public string $database,
        public string $redis,
        public string $queue,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'ok';
    }

    public function httpStatusCode(): int
    {
        return $this->isHealthy() ? 200 : 503;
    }
}
