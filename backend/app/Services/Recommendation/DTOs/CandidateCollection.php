<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;

readonly class CandidateCollection extends DataTransferObject
{
    /**
     * @param  list<Candidate>  $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function videoIds(): array
    {
        return array_map(static fn (Candidate $c): string => $c->videoId, $this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }
}
