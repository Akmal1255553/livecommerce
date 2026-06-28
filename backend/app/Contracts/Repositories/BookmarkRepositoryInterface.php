<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Bookmark;
use Illuminate\Support\Collection;

interface BookmarkRepositoryInterface extends RepositoryInterface
{
    public function exists(string $userId, string $videoId): bool;

    public function create(string $userId, string $videoId): Bookmark;

    public function delete(string $userId, string $videoId): bool;

    /**
     * @param  list<string>  $videoIds
     * @return list<string>
     */
    public function bookmarkedVideoIdsForUser(string $userId, array $videoIds): array;

    /**
     * @return Collection<int, Bookmark>
     */
    public function cursorPaginateForUser(string $userId, ?int $cursorId, int $limit): Collection;
}
