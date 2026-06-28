<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseEloquentRepository<Follow>
 */
class FollowRepository extends BaseEloquentRepository implements FollowRepositoryInterface
{
    public function __construct(Follow $model)
    {
        parent::__construct($model);
    }

    public function isFollowing(string $followerId, string $followingId): bool
    {
        return $this->model->newQuery()
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();
    }

    public function createFollow(string $followerId, string $followingId): Follow
    {
        /** @var Follow */
        return $this->model->newQuery()->create([
            'follower_id' => $followerId,
            'following_id' => $followingId,
        ]);
    }

    public function deleteFollow(string $followerId, string $followingId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->delete();
    }

    public function paginateFollowers(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->select('users.*')
            ->join('follows', 'follows.follower_id', '=', 'users.id')
            ->where('follows.following_id', $userId)
            ->with('profile')
            ->orderByDesc('follows.created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function paginateFollowing(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->select('users.*')
            ->join('follows', 'follows.following_id', '=', 'users.id')
            ->where('follows.follower_id', $userId)
            ->with('profile')
            ->orderByDesc('follows.created_at')
            ->paginate(perPage: $perPage, page: $page);
    }
}
