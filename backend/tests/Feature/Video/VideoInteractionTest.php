<?php

declare(strict_types=1);

use App\Enums\EngagementEventType;
use App\Enums\VideoStatus;
use App\Jobs\FlushVideoViewsJob;
use App\Models\Comment;
use App\Models\EngagementEvent;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

test('user can like published video', function () {
    $video = publishedVideo();
    $user = registerUser('liker1', 'liker1@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertCreated()
        ->assertJsonPath('data.liked', true)
        ->assertJsonPath('data.like_count', 1);

    test()->assertDatabaseHas('video_likes', [
        'user_id' => $user['user_id'],
        'video_id' => $video->id,
    ]);
});

test('duplicate like is idempotent', function () {
    $video = publishedVideo();
    $user = registerUser('liker2', 'liker2@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertCreated();

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertOk()
        ->assertJsonPath('data.like_count', 1);
});

test('user can unlike', function () {
    $video = publishedVideo();
    $user = registerUser('unliker', 'unliker@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertCreated();

    test()->withToken($user['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}/like")
        ->assertOk()
        ->assertJsonPath('data.liked', false)
        ->assertJsonPath('data.like_count', 0);
});

test('unlike when not liked returns not found', function () {
    $video = publishedVideo();
    $user = registerUser('nolike', 'nolike@example.com');

    test()->withToken($user['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}/like")
        ->assertNotFound();
});

test('like on non published video returns not found', function () {
    $owner = User::factory()->create();
    $video = Video::factory()->for($owner)->create(['status' => VideoStatus::Uploading]);
    $user = registerUser('badlike', 'badlike@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertNotFound();
});

test('view recorded once per session', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();

    test()->postJson("/api/v1/videos/{$video->id}/view", [
        'session_id' => $sessionId,
    ])->assertAccepted();

    test()->postJson("/api/v1/videos/{$video->id}/view", [
        'session_id' => $sessionId,
    ])->assertAccepted();

    expect((int) Redis::get("views:pending:{$video->id}"))->toBe(1);
});

test('flush video views job increments view count', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();

    test()->postJson("/api/v1/videos/{$video->id}/view", [
        'session_id' => $sessionId,
    ])->assertAccepted();

    (new FlushVideoViewsJob)->handle();

    expect($video->fresh()->view_count)->toBe(1);
});

test('user can post top level comment', function () {
    $video = publishedVideo();
    $user = registerUser('commenter', 'commenter@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'Great video!',
            'session_id' => analyticsSession(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Great video!');

    expect($video->fresh()->comment_count)->toBe(1);
});

test('user can reply to top level comment', function () {
    $video = publishedVideo();
    $user = registerUser('replier', 'replier@example.com');

    $parentId = test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'Parent comment',
            'session_id' => analyticsSession(),
        ])
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'Reply here',
            'parent_id' => $parentId,
            'session_id' => analyticsSession(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parentId);
});

test('reply to reply is rejected', function () {
    $video = publishedVideo();
    $user = registerUser('nested', 'nested@example.com');

    $parentId = test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", ['body' => 'Top level', 'session_id' => analyticsSession()])
        ->json('data.id');

    $replyId = test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'First reply',
            'parent_id' => $parentId,
            'session_id' => analyticsSession(),
        ])
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'Nested reply',
            'parent_id' => $replyId,
            'session_id' => analyticsSession(),
        ])
        ->assertStatus(422);
});

test('user can delete own comment', function () {
    $video = publishedVideo();
    $user = registerUser('deleter', 'deleter@example.com');

    $commentId = test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", ['body' => 'Delete me', 'session_id' => analyticsSession()])
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}/comments/{$commentId}")
        ->assertNoContent();

    expect(Comment::withTrashed()->find($commentId)?->trashed())->toBeTrue();
});

test('user cannot delete others comment', function () {
    $video = publishedVideo();
    $owner = registerUser('commentowner', 'commentowner@example.com');
    $other = registerUser('commentother', 'commentother@example.com');

    $commentId = test()->withToken($owner['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", ['body' => 'Not yours', 'session_id' => analyticsSession()])
        ->json('data.id');

    test()->withToken($other['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}/comments/{$commentId}")
        ->assertForbidden();
});

test('bookmark and unbookmark', function () {
    $video = publishedVideo();
    $user = registerUser('bookmarker', 'bookmarker@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/bookmark", ['session_id' => analyticsSession()])
        ->assertCreated()
        ->assertJsonPath('data.bookmarked', true);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/bookmark", ['session_id' => analyticsSession()])
        ->assertOk();

    test()->withToken($user['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}/bookmark")
        ->assertOk()
        ->assertJsonPath('data.bookmarked', false);
});

test('get bookmarks returns cursor page', function () {
    $user = registerUser('bookmarklist', 'bookmarklist@example.com');

    foreach (range(1, 3) as $i) {
        $video = publishedVideo();
        test()->withToken($user['access_token'])
            ->postJson("/api/v1/videos/{$video->id}/bookmark", ['session_id' => analyticsSession()])
            ->assertCreated();
    }

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/bookmarks?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonCount(2, 'data');
});

test('share increments share count', function () {
    $video = publishedVideo();
    $user = registerUser('sharer', 'sharer@example.com');
    $sessionId = (string) Str::uuid();

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/share", [
            'channel' => 'link',
            'session_id' => $sessionId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.share_count', 1);

    expect($video->fresh()->share_count)->toBe(1);
});

test('video resource includes is liked for liker in feed and detail', function () {
    $video = publishedVideo();
    $user = registerUser('feedliker', 'feedliker@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/like", ['session_id' => analyticsSession()])
        ->assertCreated();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonPath('data.0.is_liked', true);

    test()->withToken($user['access_token'])
        ->getJson("/api/v1/videos/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.is_liked', true);
});

test('video resource includes is bookmarked in feed and detail', function () {
    $video = publishedVideo();
    $user = registerUser('feedbookmark', 'feedbookmark@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/bookmark", ['session_id' => analyticsSession()])
        ->assertCreated();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonPath('data.0.is_bookmarked', true);

    test()->withToken($user['access_token'])
        ->getJson("/api/v1/videos/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.is_bookmarked', true);
});

test('watch time metric persisted', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();

    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'watch_time',
                'session_id' => $sessionId,
                'video_id' => $video->id,
                'payload' => ['seconds' => 5, 'position' => 12],
            ],
        ],
    ])->assertAccepted();

    test()->assertDatabaseHas('engagement_events', [
        'event_type' => EngagementEventType::WatchTime->value,
        'video_id' => $video->id,
        'session_id' => $sessionId,
    ]);
});

test('video progress 100 metric persisted', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();

    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'video_progress_100',
                'session_id' => $sessionId,
                'video_id' => $video->id,
                'payload' => ['percent' => 100, 'position' => 30],
            ],
        ],
    ])->assertAccepted();

    expect(EngagementEvent::query()
        ->where('event_type', EngagementEventType::VideoProgress100->value)
        ->where('video_id', $video->id)
        ->exists())->toBeTrue();
});

test('owner can put metadata', function () {
    $auth = registerUser('metaowner', 'metaowner@example.com');
    $video = publishedVideo(User::query()->find($auth['user_id']));

    test()->withToken($auth['access_token'])
        ->putJson("/api/v1/videos/{$video->id}", [
            'title' => 'Updated title',
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.description', 'Updated description');
});

test('owner can soft delete video and it is excluded from feed', function () {
    $auth = registerUser('deleteowner', 'deleteowner@example.com');
    $video = publishedVideo(User::query()->find($auth['user_id']));

    test()->withToken($auth['access_token'])
        ->deleteJson("/api/v1/videos/{$video->id}")
        ->assertNoContent();

    expect($video->fresh()->trashed())->toBeTrue();

    test()->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
