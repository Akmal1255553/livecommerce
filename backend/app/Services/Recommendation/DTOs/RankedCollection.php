<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;

readonly class RankedCollection extends DataTransferObject
{
    /**
     * @param  list<RankedItem>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $snapshot = null,
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
        return array_map(static fn (RankedItem $item): string => $item->videoId, $this->items);
    }

    public function withSnapshot(string $snapshot): self
    {
        return new self(items: $this->items, snapshot: $snapshot);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
