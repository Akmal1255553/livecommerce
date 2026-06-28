<?php

declare(strict_types=1);

use App\Enums\EngagementEventType;
use App\Models\EngagementEvent;
use Illuminate\Support\Str;

test('analytics funnel events can be recorded in order', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();
    $creatorId = $video->user_id;

    $events = [
        ['type' => 'feed_open', 'session_id' => $sessionId, 'payload' => ['tab' => 'for_you']],
        ['type' => 'video_impression', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['position' => 0, 'visible_ms' => 500]],
        ['type' => 'video_start', 'session_id' => $sessionId, 'video_id' => $video->id],
        ['type' => 'video_progress_25', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['percent' => 25, 'position' => 8]],
        ['type' => 'video_progress_50', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['percent' => 50, 'position' => 15]],
        ['type' => 'video_progress_75', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['percent' => 75, 'position' => 22]],
        ['type' => 'video_progress_100', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['percent' => 100, 'position' => 30]],
        ['type' => 'watch_time', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['seconds' => 5, 'position' => 30]],
        ['type' => 'follow_after_watch', 'session_id' => $sessionId, 'video_id' => $video->id, 'payload' => ['creator_id' => $creatorId]],
    ];

    test()->postJson('/api/v1/metrics/events', ['events' => $events])
        ->assertAccepted()
        ->assertJsonPath('data.accepted', count($events));

    expect(EngagementEvent::query()->where('session_id', $sessionId)->count())->toBe(count($events));
});

test('interaction apis record analytics events with session id', function () {
    $video = publishedVideo();
    $user = registerUser('analytics', 'analytics@example.com');
    $sessionId = (string) Str::uuid();

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => $sessionId])
        ->assertCreated();

    test()->assertDatabaseHas('engagement_events', [
        'event_type' => EngagementEventType::Like->value,
        'video_id' => $video->id,
        'session_id' => $sessionId,
        'user_id' => $user['user_id'],
    ]);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/bookmark", ['session_id' => $sessionId])
        ->assertCreated();

    test()->assertDatabaseHas('engagement_events', [
        'event_type' => EngagementEventType::Save->value,
        'video_id' => $video->id,
        'session_id' => $sessionId,
    ]);

    test()->postJson("/api/v1/videos/{$video->id}/view", ['session_id' => $sessionId])
        ->assertAccepted();

    test()->assertDatabaseHas('engagement_events', [
        'event_type' => EngagementEventType::View->value,
        'video_id' => $video->id,
        'session_id' => $sessionId,
    ]);
});

test('progress milestone rejects wrong percent', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();

    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'video_progress_50',
                'session_id' => $sessionId,
                'video_id' => $video->id,
                'payload' => ['percent' => 25, 'position' => 10],
            ],
        ],
    ])->assertStatus(422);
});

test('engagement event type funnel includes recommendation signals', function () {
    $funnel = EngagementEventType::funnel();

    expect($funnel)->toContain(EngagementEventType::VideoProgress100)
        ->and($funnel)->toContain(EngagementEventType::FollowAfterWatch)
        ->and($funnel)->toContain(EngagementEventType::Save)
        ->and(count($funnel))->toBe(13);
});
