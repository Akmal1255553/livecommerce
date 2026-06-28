<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;

readonly class Candidate extends DataTransferObject
{
    /**
     * @param  list<string>  $sources
     */
    public function __construct(
        public string $videoId,
        public array $sources = [],
    ) {}

    /**
     * @param  list<string>  $sources
     */
    public function withSources(array $sources): self
    {
        return new self(
            videoId: $this->videoId,
            sources: array_values(array_unique([...$this->sources, ...$sources])),
        );
    }
}
