<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\DTOs\Notification\NotificationData;
use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\NotificationType;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\BaseService;

class NotificationService extends BaseService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserDeviceRepositoryInterface $devices,
    ) {}

    public function notify(
        User $recipient,
        NotificationType $type,
        string $title,
        string $body,
        NotificationData $data,
        bool $sendPush = true,
    ): ?Notification {
        if (! $this->isInAppTypeEnabled($recipient, $type->value)) {
            return null;
        }

        $payload = $data->toArray();

        $notification = $this->notifications->create([
            'user_id' => $recipient->id,
            'type' => $type->value,
            'title' => $title,
            'body' => $body,
            'data' => $payload,
        ]);

        if ($sendPush && $this->shouldSendPush($recipient, $type->value)) {
            SendPushNotificationJob::dispatch(
                $recipient->id,
                $title,
                $body,
                $payload,
            );
        }

        return $notification;
    }

    public function notifyNewFollower(User $recipient, User $follower): ?Notification
    {
        $profile = $follower->profile;
        $displayName = $profile !== null ? $profile->display_name : $follower->username;

        return $this->notify(
            recipient: $recipient,
            type: NotificationType::NEW_FOLLOWER,
            title: 'New follower',
            body: "{$displayName} started following you.",
            data: NotificationData::fromUser(
                user: $follower,
                entity_type: 'user',
                entity_id: $follower->id,
                deep_link: "/profile/{$follower->id}",
            ),
        );
    }

    /**
     * @return CursorPaginationData<Notification>
     */
    public function listForUser(User $user, ?string $cursor, int $limit): CursorPaginationData
    {
        $beforeId = CursorPaginationData::decodeCursor($cursor);
        $items = $this->notifications->cursorPaginateForUser($user->id, $beforeId, $limit);
        $hasMore = $items->count() > $limit;

        if ($hasMore) {
            $items = $items->take($limit);
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? CursorPaginationData::encodeCursor((int) $items->last()->id)
            : null;

        return new CursorPaginationData($items, $nextCursor, $hasMore, $limit);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->countUnread($user->id);
    }

    public function markRead(User $user, int $notificationId): Notification
    {
        $notification = $this->notifications->markRead($notificationId, $user->id);

        if ($notification === null) {
            throw new ResourceNotFoundException('Notification not found.');
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return $this->notifications->markAllRead($user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function registerDevice(User $user, array $data): UserDevice
    {
        return $this->devices->upsertDevice([
            'user_id' => $user->id,
            'fcm_token' => $data['fcm_token'],
            'platform' => $data['platform'],
            'device_id' => $data['device_id'] ?? null,
            'app_version' => $data['app_version'] ?? null,
        ]);
    }

    public function unregisterDevice(User $user, string $fcmToken): void
    {
        if (! $this->devices->deactivate($user->id, $fcmToken)) {
            throw new ResourceNotFoundException('Device not found.');
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(User $user, array $settings): User
    {
        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => $user->username, 'notification_settings' => []],
        );

        $current = $profile->notification_settings ?? [];

        if (isset($settings['push_enabled'])) {
            $current['push_enabled'] = (bool) $settings['push_enabled'];
        }

        if (isset($settings['email_enabled'])) {
            $current['email_enabled'] = (bool) $settings['email_enabled'];
        }

        if (isset($settings['types']) && is_array($settings['types'])) {
            $current['types'] = array_merge($current['types'] ?? [], $settings['types']);
        }

        $profile->update(['notification_settings' => $current]);

        return $user->fresh(['profile']);
    }

    private function isInAppTypeEnabled(User $user, string $type): bool
    {
        $profile = $user->profile;
        $settings = $profile !== null ? ($profile->notification_settings ?? []) : [];
        $types = $settings['types'] ?? [];

        if (array_key_exists($type, $types)) {
            return (bool) $types[$type];
        }

        return true;
    }

    private function shouldSendPush(User $user, string $type): bool
    {
        $profile = $user->profile;
        $settings = $profile !== null ? ($profile->notification_settings ?? []) : [];

        if (isset($settings['push_enabled']) && ! $settings['push_enabled']) {
            return false;
        }

        return $this->isInAppTypeEnabled($user, $type);
    }
}
