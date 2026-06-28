<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Events\UserFollowed;
use App\Models\User;
use App\Services\Notification\NotificationService;

class NotifyOnFollow
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function handle(UserFollowed $event): void
    {
        $follower = $this->users->findById($event->followerId);
        $followed = $this->users->findById($event->followingId);

        if (! $follower instanceof User || ! $followed instanceof User) {
            return;
        }

        $follower->loadMissing('profile');
        $followed->loadMissing('profile');

        $this->notifications->notifyNewFollower($followed, $follower);
    }
}
