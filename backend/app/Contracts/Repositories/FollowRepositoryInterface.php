<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FollowRepositoryInterface extends RepositoryInterface
{
    public function isFollowing(string $followerId, string $followingId): bool;

    public function createFollow(string $followerId, string $followingId): Follow;

    public function deleteFollow(string $followerId, string $followingId): bool;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFollowers(string $userId, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFollowing(string $userId, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return list<string>
     */
    public function followerIds(string $userId): array;
}
