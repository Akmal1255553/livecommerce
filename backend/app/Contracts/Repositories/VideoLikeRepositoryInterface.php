<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\VideoLike;

interface VideoLikeRepositoryInterface extends RepositoryInterface
{
    public function exists(string $userId, string $videoId): bool;

    public function create(string $userId, string $videoId): VideoLike;

    public function delete(string $userId, string $videoId): bool;

    /**
     * @param  list<string>  $videoIds
     * @return list<string>
     */
    public function likedVideoIdsForUser(string $userId, array $videoIds): array;
}
