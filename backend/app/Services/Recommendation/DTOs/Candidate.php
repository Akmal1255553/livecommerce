<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;
use App\Enums\ContentType;

readonly class Candidate extends DataTransferObject
{
    /**
     * @param  list<string>  $sources
     */
    public function __construct(
        public string $videoId,
        public array $sources = [],
        public ContentType $type = ContentType::Video,
    ) {}

    /**
     * @param  list<string>  $sources
     */
    public function withSources(array $sources): self
    {
        return new self(
            videoId: $this->videoId,
            sources: array_values(array_unique([...$this->sources, ...$sources])),
            type: $this->type,
        );
    }

    public function isVideo(): bool
    {
        return $this->type === ContentType::Video;
    }

    public function isLive(): bool
    {
        return $this->type === ContentType::Live;
    }

    public function key(): string
    {
        return $this->type->value.':'.$this->videoId;
    }
}
