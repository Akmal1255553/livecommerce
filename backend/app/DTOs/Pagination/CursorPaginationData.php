<?php

declare(strict_types=1);

namespace App\DTOs\Pagination;

use App\DTOs\DataTransferObject;
use Illuminate\Support\Collection;

/**
 * @template T
 */
readonly class CursorPaginationData extends DataTransferObject
{
    /**
     * @param  Collection<int, T>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
        public bool $hasMore,
        public int $limit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'next_cursor' => $this->nextCursor,
            'prev_cursor' => null,
            'has_more' => $this->hasMore,
            'limit' => $this->limit,
        ];
    }

    public static function encodeCursor(int $id): string
    {
        return base64_encode((string) json_encode(['id' => $id], JSON_THROW_ON_ERROR));
    }

    public static function decodeCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);

        if (! is_array($decoded) || ! isset($decoded['id'])) {
            return null;
        }

        return (int) $decoded['id'];
    }
}
