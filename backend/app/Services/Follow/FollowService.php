<?php

declare(strict_types=1);

namespace App\Services\Follow;

use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Events\UserFollowed;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BaseService;
use App\Services\User\ProfileService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FollowService extends BaseService
{
    public function __construct(
        private readonly FollowRepositoryInterface $follows,
        private readonly UserRepositoryInterface $users,
        private readonly ProfileService $profiles,
    ) {}

    public function follow(User $follower, string $targetId): void
    {
        if ($follower->id === $targetId) {
            throw new ConflictException('You cannot follow yourself.');
        }

        $target = $this->users->findById($targetId);

        if (! $target instanceof User) {
            throw new ResourceNotFoundException('User not found.');
        }

        DB::transaction(function () use ($follower, $targetId): void {
            if ($this->follows->isFollowing($follower->id, $targetId)) {
                throw new ConflictException('Already following this user.');
            }

            $this->follows->createFollow($follower->id, $targetId);
            $this->adjustCounters($follower->id, $targetId, increment: true);
        });

        $this->profiles->forgetProfileCache($follower->id);
        $this->profiles->forgetProfileCache($targetId);

        event(new UserFollowed($follower->id, $targetId));
    }

    public function unfollow(User $follower, string $targetId): void
    {
        DB::transaction(function () use ($follower, $targetId): void {
            if (! $this->follows->isFollowing($follower->id, $targetId)) {
                throw new ResourceNotFoundException('Not following this user.');
            }

            $this->follows->deleteFollow($follower->id, $targetId);
            $this->adjustCounters($follower->id, $targetId, increment: false);
        });

        $this->profiles->forgetProfileCache($follower->id);
        $this->profiles->forgetProfileCache($targetId);
    }

    public function getPublicProfile(string $userId, ?User $viewer): User
    {
        $user = $this->profiles->getCachedUserWithProfile($userId);

        if ($viewer !== null && $viewer->id !== $userId) {
            $user->setAttribute(
                'is_following',
                $this->follows->isFollowing($viewer->id, $userId),
            );
        }

        return $user;
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFollowers(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        $this->ensureUserExists($userId);

        return $this->follows->paginateFollowers($userId, $page, $perPage);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFollowing(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        $this->ensureUserExists($userId);

        return $this->follows->paginateFollowing($userId, $page, $perPage);
    }

    private function ensureUserExists(string $userId): void
    {
        if ($this->users->findById($userId) === null) {
            throw new ResourceNotFoundException('User not found.');
        }
    }

    private function adjustCounters(string $followerId, string $followingId, bool $increment): void
    {
        $targetProfile = UserProfile::query()
            ->where('user_id', $followingId)
            ->lockForUpdate()
            ->firstOrFail();

        $followerProfile = UserProfile::query()
            ->where('user_id', $followerId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($increment) {
            $targetProfile->increment('follower_count');
            $followerProfile->increment('following_count');

            return;
        }

        if ($targetProfile->follower_count > 0) {
            $targetProfile->decrement('follower_count');
        }

        if ($followerProfile->following_count > 0) {
            $followerProfile->decrement('following_count');
        }
    }
}
