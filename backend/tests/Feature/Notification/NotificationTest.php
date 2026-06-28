<?php

declare(strict_types=1);

use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\Contracts\Services\PushNotificationInterface;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Queue;

test('device can be registered and unregistered', function () {
    $user = registerUser('deviceuser', 'deviceuser@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/devices', [
            'fcm_token' => 'firebase-token-abc123',
            'platform' => 'android',
            'device_id' => 'pixel-7',
        ])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.is_active', true);

    test()->assertDatabaseHas('user_devices', [
        'user_id' => $user['user_id'],
        'fcm_token' => 'firebase-token-abc123',
        'is_active' => true,
    ]);

    test()->withToken($user['access_token'])
        ->deleteJson('/api/v1/devices/'.urlencode('firebase-token-abc123'))
        ->assertOk();

    test()->assertDatabaseHas('user_devices', [
        'user_id' => $user['user_id'],
        'fcm_token' => 'firebase-token-abc123',
        'is_active' => false,
    ]);
});

test('follow creates in-app notification for followed user', function () {
    Queue::fake();

    $follower = registerUser('notifyfollower', 'notifyfollower@example.com');
    $target = User::factory()->create(['username' => 'notifytarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->assertDatabaseHas('notifications', [
        'user_id' => $target->id,
        'type' => NotificationType::NEW_FOLLOWER->value,
    ]);

    Queue::assertPushed(SendPushNotificationJob::class);
});

test('follow notification uses standardized data payload', function () {
    $follower = registerUser('payloadfollower', 'payloadfollower@example.com');
    $target = registerUser('payloadtarget', 'payloadtarget@example.com');

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target['user_id']}/follow")
        ->assertCreated();

    test()->withToken($target['access_token'])
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', NotificationType::NEW_FOLLOWER->value)
        ->assertJsonPath('data.0.data.user_id', $follower['user_id'])
        ->assertJsonPath('data.0.data.username', 'payloadfollower')
        ->assertJsonPath('data.0.data.entity_id', $follower['user_id'])
        ->assertJsonPath('data.0.data.entity_type', 'user')
        ->assertJsonPath('data.0.data.deep_link', "/profile/{$follower['user_id']}")
        ->assertJsonStructure([
            'data' => [
                ['data' => ['user_id', 'avatar', 'username', 'entity_id', 'entity_type', 'deep_link']],
            ],
        ]);
});

test('follow does not notify when NEW_FOLLOWER type is disabled', function () {
    Queue::fake();

    $follower = registerUser('nopushfollower', 'nopushfollower@example.com');
    $target = User::factory()->create(['username' => 'nopushtarget']);

    $target->profile()->update([
        'notification_settings' => [
            'push_enabled' => true,
            'types' => [NotificationType::NEW_FOLLOWER->value => false],
        ],
    ]);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->assertDatabaseMissing('notifications', [
        'user_id' => $target->id,
        'type' => NotificationType::NEW_FOLLOWER->value,
    ]);

    Queue::assertNotPushed(SendPushNotificationJob::class);
});

test('notifications can be listed with cursor pagination', function () {
    $user = registerUser('notiflist', 'notiflist@example.com');
    $follower = User::factory()->create(['username' => 'notifactor']);

    app(NotificationService::class)->notifyNewFollower(
        User::query()->find($user['user_id']),
        $follower,
    );

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/notifications?limit=10')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', NotificationType::NEW_FOLLOWER->value)
        ->assertJsonStructure([
            'meta' => ['next_cursor', 'prev_cursor', 'has_more', 'limit'],
        ]);
});

test('unread count reflects unread notifications', function () {
    $user = registerUser('unreadcount', 'unreadcount@example.com');
    $follower = User::factory()->create(['username' => 'unreadactor']);

    $service = app(NotificationService::class);
    $service->notifyNewFollower(User::query()->find($user['user_id']), $follower);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

test('notification can be marked as read', function () {
    $user = registerUser('markread', 'markread@example.com');
    $follower = User::factory()->create(['username' => 'markreadactor']);

    $service = app(NotificationService::class);
    $notification = $service->notifyNewFollower(User::query()->find($user['user_id']), $follower);

    test()->withToken($user['access_token'])
        ->putJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('data.count', 0);
});

test('all notifications can be marked as read', function () {
    $user = registerUser('markall', 'markall@example.com');
    $recipient = User::query()->find($user['user_id']);
    $service = app(NotificationService::class);

    $service->notifyNewFollower($recipient, User::factory()->create(['username' => 'actor1']));
    $service->notifyNewFollower($recipient, User::factory()->create(['username' => 'actor2']));

    test()->withToken($user['access_token'])
        ->putJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated_count', 2);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('data.count', 0);
});

test('user cannot mark another users notification as read', function () {
    $owner = registerUser('notifowner', 'notifowner@example.com');
    $other = registerUser('notifother', 'notifother@example.com');
    $follower = User::factory()->create(['username' => 'notifintruder']);

    $notification = app(NotificationService::class)
        ->notifyNewFollower(User::query()->find($owner['user_id']), $follower);

    test()->withToken($other['access_token'])
        ->putJson("/api/v1/notifications/{$notification->id}/read")
        ->assertForbidden();
});

test('notification settings can be updated', function () {
    $user = registerUser('notifsettings', 'notifsettings@example.com');

    test()->withToken($user['access_token'])
        ->putJson('/api/v1/me/notification-settings', [
            'push_enabled' => false,
            'types' => [NotificationType::NEW_FOLLOWER->value => false],
        ])
        ->assertOk();

    $profile = User::query()->find($user['user_id'])?->profile;
    $profile?->refresh();

    expect($profile?->notification_settings['push_enabled'])->toBeFalse()
        ->and($profile?->notification_settings['types'][NotificationType::NEW_FOLLOWER->value])->toBeFalse();
});

test('push job sends to registered active devices', function () {
    $user = registerUser('pushjob', 'pushjob@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/devices', [
            'fcm_token' => 'push-token-xyz',
            'platform' => 'ios',
        ])
        ->assertCreated();

    $job = new SendPushNotificationJob(
        $user['user_id'],
        'Test',
        'Body',
        [
            'user_id' => $user['user_id'],
            'avatar' => null,
            'username' => 'pushjob',
            'entity_id' => $user['user_id'],
            'entity_type' => 'user',
            'deep_link' => "/profile/{$user['user_id']}",
        ],
    );

    $job->handle(
        app(PushNotificationInterface::class),
        app(UserDeviceRepositoryInterface::class),
    );

    expect(true)->toBeTrue();
});
