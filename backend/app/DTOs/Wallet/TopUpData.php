<?php

declare(strict_types=1);

namespace App\DTOs\Wallet;

final readonly class TopUpData
{
    public function __construct(
        public int $amount,
        public string $method,
        public ?string $reference = null,
    ) {}
}
