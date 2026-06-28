<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Carbon;

test('for-you feed returns published public videos with cursor meta', function () {
    $creator = User::factory()->create(['username' => 'feedcreator']);
    Video::factory()->count(3)->for($creator)->published()->create();

    test()->getJson('/api/v1/feed/for-you?limit=2')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonStructure([
            'data' => [
                ['id', 'user', 'title', 'video_url', 'thumbnail_url', 'duration', 'view_count', 'like_count', 'comment_count', 'is_liked', 'is_bookmarked', 'products', 'status', 'created_at'],
            ],
            'meta' => ['next_cursor', 'prev_cursor', 'has_more', 'limit'],
        ]);
});

test('for-you feed excludes draft and non-public videos', function () {
    $creator = User::factory()->create();
    Video::factory()->for($creator)->published()->create(['title' => 'Visible']);
    Video::factory()->for($creator)->draft()->create(['title' => 'Draft']);
    Video::factory()->for($creator)->published()->create([
        'title' => 'Private',
        'visibility' => VideoVisibility::Private,
    ]);

    $response = test()->getJson('/api/v1/feed/for-you')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())
        ->toBe(['Visible']);
});

test('for-you feed cursor pagination returns next page', function () {
    $creator = User::factory()->create();
    $now = Carbon::parse('2026-06-01 12:00:00');

    Video::factory()->for($creator)->published()->create([
        'title' => 'Newest',
        'created_at' => $now,
    ]);
    Video::factory()->for($creator)->published()->create([
        'title' => 'Middle',
        'created_at' => $now->copy()->subHour(),
    ]);
    Video::factory()->for($creator)->published()->create([
        'title' => 'Oldest',
        'created_at' => $now->copy()->subHours(2),
    ]);

    $first = test()->getJson('/api/v1/feed/for-you?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.has_more', true);

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = test()->getJson('/api/v1/feed/for-you?limit=2&cursor='.urlencode($cursor))
        ->assertOk()
        ->assertJsonPath('meta.has_more', false);

    expect(collect($first->json('data'))->pluck('title')->all())->toBe(['Newest', 'Middle'])
        ->and(collect($second->json('data'))->pluck('title')->all())->toBe(['Oldest']);
});

test('following feed requires authentication', function () {
    test()->getJson('/api/v1/feed/following')
        ->assertUnauthorized();
});

test('following feed returns videos from followed users only', function () {
    $viewer = registerUser('feedviewer', 'feedviewer@example.com');
    $followed = User::factory()->create(['username' => 'followedcreator']);
    $other = User::factory()->create(['username' => 'othercreator']);

    test()->withToken($viewer['access_token'])
        ->postJson("/api/v1/users/{$followed->id}/follow")
        ->assertCreated();

    Video::factory()->for($followed)->published()->create(['title' => 'From followed']);
    Video::factory()->for($other)->published()->create(['title' => 'From other']);

    test()->withToken($viewer['access_token'])
        ->getJson('/api/v1/feed/following')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'From followed')
        ->assertJsonPath('data.0.user.username', 'followedcreator');
});

test('following feed returns empty list when user follows nobody', function () {
    $viewer = registerUser('lonelyviewer', 'lonelyviewer@example.com');
    $creator = User::factory()->create();
    Video::factory()->for($creator)->published()->create();

    test()->withToken($viewer['access_token'])
        ->getJson('/api/v1/feed/following')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);
});

test('video resource includes compact user payload', function () {
    $creator = User::factory()->create([
        'username' => 'compactuser',
        'avatar_url' => 'https://cdn.example.com/avatar.jpg',
        'is_verified' => true,
    ]);
    Video::factory()->for($creator)->published()->create(['status' => VideoStatus::Published]);

    test()->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonPath('data.0.user.id', $creator->id)
        ->assertJsonPath('data.0.user.username', 'compactuser')
        ->assertJsonPath('data.0.user.avatar_url', 'https://cdn.example.com/avatar.jpg')
        ->assertJsonPath('data.0.user.is_verified', true);
});
