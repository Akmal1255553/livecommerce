<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;

readonly class RankedItem extends DataTransferObject
{
    /**
     * @param  array<string, float|int>  $signals
     * @param  list<string>  $sources
     */
    public function __construct(
        public string $videoId,
        public float $score = 0.0,
        public array $signals = [],
        public array $sources = [],
    ) {}

    /**
     * @param  array<string, float>  $signals
     */
    public function withScore(float $score, array $signals = []): self
    {
        return new self(
            videoId: $this->videoId,
            score: $score,
            signals: $signals !== [] ? $signals : $this->signals,
            sources: $this->sources,
        );
    }
}
