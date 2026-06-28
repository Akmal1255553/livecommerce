<?php

declare(strict_types=1);

namespace App\DTOs\Video;

final readonly class HlsProfile
{
    public function __construct(
        public string $name,
        public int $height,
        public int $videoBitrateKbps,
        public int $audioBitrateKbps = 128,
    ) {}
}
