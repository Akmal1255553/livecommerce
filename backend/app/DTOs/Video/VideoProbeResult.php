<?php

declare(strict_types=1);

namespace App\DTOs\Video;

final readonly class VideoProbeResult
{
    public function __construct(
        public int $duration,
        public int $width,
        public int $height,
        public string $codec,
        public ?int $bitrateKbps = null,
    ) {}
}
