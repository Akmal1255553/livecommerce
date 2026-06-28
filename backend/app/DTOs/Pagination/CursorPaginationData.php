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

    public static function encodeVideoCursor(string $id, string $createdAt): string
    {
        return base64_encode((string) json_encode([
            'id' => $id,
            'created_at' => $createdAt,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{id: string, created_at: string}|null
     */
    public static function decodeVideoCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);

        if (! is_array($decoded) || ! isset($decoded['id'], $decoded['created_at'])) {
            return null;
        }

        return [
            'id' => (string) $decoded['id'],
            'created_at' => (string) $decoded['created_at'],
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Video>  $items
     */
    public static function nextVideoCursor(Collection $items, bool $hasMore): ?string
    {
        if (! $hasMore || $items->isEmpty()) {
            return null;
        }

        /** @var \App\Models\Video $last */
        $last = $items->last();

        return self::encodeVideoCursor(
            $last->id,
            $last->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
