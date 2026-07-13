<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Notification\NotificationData;
use App\Enums\NotificationType;
use App\Events\LiveSessionStarted;
use App\Models\User;
use App\Services\Notification\NotificationService;

class NotifyOnLiveStarted
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly FollowRepositoryInterface $follows,
    ) {}

    public function handle(LiveSessionStarted $event): void
    {
        $session = $event->session;
        $seller = $this->users->findById($session->seller_id);

        if (! $seller instanceof User) {
            return;
        }

        $seller->loadMissing('profile');
        $displayName = $seller->profile?->display_name ?? $seller->username;

        $followerIds = $this->follows->followerIds($seller->id);

        foreach ($followerIds as $followerId) {
            $follower = $this->users->findById($followerId);

            if (! $follower instanceof User) {
                continue;
            }

            $this->notifications->notify(
                recipient: $follower,
                type: NotificationType::LIVE_STARTED,
                title: 'Live now',
                body: "{$displayName} started a live session.",
                data: NotificationData::fromUser(
                    user: $seller,
                    entity_type: 'live',
                    entity_id: (string) $session->id,
                    deep_link: '/live/'.$session->id,
                ),
            );
        }
    }
}
