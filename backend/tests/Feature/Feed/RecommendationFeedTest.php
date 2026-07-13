<?php

declare(strict_types=1);

use App\Enums\EngagementEventType;
use App\Enums\VideoVisibility;
use App\Models\EngagementEvent;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoEngagementRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Cache::flush();
});

test('trending feed returns published public videos only', function () {
    $creator = User::factory()->create();
    Video::factory()->for($creator)->published()->create(['title' => 'Visible']);
    Video::factory()->for($creator)->draft()->create(['title' => 'Draft']);
    Video::factory()->for($creator)->published()->create([
        'title' => 'Private',
        'visibility' => VideoVisibility::Private,
    ]);

    $response = test()->getJson('/api/v1/feed/trending')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Visible']);
});

test('trending feed orders by 24h rollup score', function () {
    $creator = User::factory()->create();
    $low = Video::factory()->for($creator)->published()->create(['title' => 'Low']);
    $high = Video::factory()->for($creator)->published()->create(['title' => 'High']);
    $bucket = Carbon::now()->utc()->startOfHour();

    VideoEngagementRollup::query()->create([
        'video_id' => $low->id,
        'bucket_hour' => $bucket,
        'views' => 100,
        'likes' => 1,
        'completions' => 5,
    ]);

    VideoEngagementRollup::query()->create([
        'video_id' => $high->id,
        'bucket_hour' => $bucket,
        'views' => 100,
        'likes' => 50,
        'completions' => 80,
    ]);

    $response = test()->getJson('/api/v1/feed/trending')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['High', 'Low']);
});

test('popular feed orders by engagement counters', function () {
    $creator = User::factory()->create();
    Video::factory()->for($creator)->published()->create(['title' => 'Less', 'like_count' => 1]);
    Video::factory()->for($creator)->published()->create(['title' => 'More', 'like_count' => 100]);

    $response = test()->getJson('/api/v1/feed/popular')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['More', 'Less']);
});

test('new feed returns chronological order', function () {
    $creator = User::factory()->create();
    $now = Carbon::parse('2026-06-01 12:00:00');

    Video::factory()->for($creator)->published()->create([
        'title' => 'Newest',
        'created_at' => $now,
    ]);
    Video::factory()->for($creator)->published()->create([
        'title' => 'Older',
        'created_at' => $now->copy()->subHour(),
    ]);

    $response = test()->getJson('/api/v1/feed/new')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Newest', 'Older'])
        ->and($response->json('meta.strategy'))->toBe('new');
});

test('ranked feed cursor pagination works', function () {
    $creator = User::factory()->create();
    Video::factory()->count(3)->for($creator)->published()->sequence(
        ['title' => 'A', 'like_count' => 30],
        ['title' => 'B', 'like_count' => 20],
        ['title' => 'C', 'like_count' => 10],
    )->create();

    $first = test()->getJson('/api/v1/feed/popular?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.has_more', true);

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = test()->getJson('/api/v1/feed/popular?limit=2&cursor='.urlencode($cursor))
        ->assertOk()
        ->assertJsonPath('meta.has_more', false);

    expect(collect($first->json('data')))->toHaveCount(2)
        ->and(collect($second->json('data')))->toHaveCount(1);
});

test('ranked feed meta includes strategy and engine', function () {
    Video::factory()->published()->create();

    test()->getJson('/api/v1/feed/trending')
        ->assertOk()
        ->assertJsonPath('meta.strategy', 'trending')
        ->assertJsonPath('meta.engine', 'rule')
        ->assertJsonStructure(['meta' => ['snapshot']]);
});

test('for you feed excludes completed videos for authenticated user', function () {
    $viewer = registerUser('foryouviewer', 'foryouviewer@example.com');
    $video = publishedVideo();
    $other = publishedVideo();

    EngagementEvent::query()->create([
        'event_type' => EngagementEventType::VideoProgress100,
        'user_id' => $viewer['user_id'],
        'video_id' => $video->id,
        'session_id' => (string) Str::uuid(),
        'payload' => ['percent' => 100],
        'created_at' => now(),
    ]);

    $response = test()->withToken($viewer['access_token'])
        ->getJson('/api/v1/feed/for-you')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($other->id)
        ->and($ids)->not->toContain($video->id);
});

test('for you feed excludes hard skip videos', function () {
    $viewer = registerUser('skipviewer', 'skipviewer@example.com');
    $skipped = publishedVideo();
    $visible = publishedVideo();

    EngagementEvent::query()->create([
        'event_type' => EngagementEventType::Skip,
        'user_id' => $viewer['user_id'],
        'video_id' => $skipped->id,
        'session_id' => (string) Str::uuid(),
        'payload' => ['seconds' => 1],
        'created_at' => now(),
    ]);

    $response = test()->withToken($viewer['access_token'])
        ->getJson('/api/v1/feed/for-you')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not->toContain($skipped->id);
});

test('anonymous for you feed returns 200', function () {
    publishedVideo();

    test()->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.strategy', 'for_you');
});

test('following feed regression remains chronological', function () {
    $viewer = registerUser('followreg', 'followreg@example.com');
    $followed = User::factory()->create();
    $now = Carbon::parse('2026-06-01 12:00:00');

    test()->withToken($viewer['access_token'])
        ->postJson("/api/v1/users/{$followed->id}/follow")
        ->assertCreated();

    Video::factory()->for($followed)->published()->create([
        'title' => 'Newest',
        'created_at' => $now,
    ]);
    Video::factory()->for($followed)->published()->create([
        'title' => 'Older',
        'created_at' => $now->copy()->subHour(),
    ]);

    test()->withToken($viewer['access_token'])
        ->getJson('/api/v1/feed/following')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Newest')
        ->assertJsonPath('data.1.title', 'Older');
});

test('for you diversity limits consecutive same author', function () {
    $authorA = User::factory()->create();
    $authorB = User::factory()->create();

    Video::factory()->count(3)->for($authorA)->published()->sequence(
        ['like_count' => 50],
        ['like_count' => 49],
        ['like_count' => 48],
    )->create();

    Video::factory()->count(3)->for($authorB)->published()->sequence(
        ['like_count' => 40],
        ['like_count' => 39],
        ['like_count' => 38],
    )->create();

    $response = test()->getJson('/api/v1/feed/for-you?limit=20')->assertOk();
    $authorIds = collect($response->json('data'))->pluck('payload.user.id')->all();

    $streak = 1;
    $maxStreak = 1;

    for ($i = 1, $count = count($authorIds); $i < $count; $i++) {
        if ($authorIds[$i] === $authorIds[$i - 1]) {
            $streak++;
            $maxStreak = max($maxStreak, $streak);
        } else {
            $streak = 1;
        }
    }

    expect($maxStreak)->toBeLessThanOrEqual(2);
});

test('exploration slot can surface low view video', function () {
    $creator = User::factory()->create();
    Video::factory()->count(20)->for($creator)->published()->create([
        'view_count' => 5000,
        'like_count' => 100,
    ]);

    $fresh = Video::factory()->for($creator)->published()->create([
        'title' => 'FreshLowViews',
        'view_count' => 10,
        'like_count' => 0,
        'published_at' => now(),
    ]);

    $response = test()->getJson('/api/v1/feed/for-you?limit=20')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($fresh->id);
});

test('config weight change affects feed order', function () {
    config(['recommendation.scoring.weights.freshness' => 0.99]);
    config(['recommendation.scoring.weights.like' => 0.01]);

    $creator = User::factory()->create();
    $oldPopular = Video::factory()->for($creator)->published()->create([
        'title' => 'OldPopular',
        'like_count' => 1000,
        'published_at' => now()->subDays(30),
    ]);
    $fresh = Video::factory()->for($creator)->published()->create([
        'title' => 'Fresh',
        'like_count' => 0,
        'published_at' => now(),
    ]);

    $response = test()->getJson('/api/v1/feed/for-you?limit=2')->assertOk();

    expect(collect($response->json('data'))->pluck('payload.title')->first())->toBe('Fresh')
        ->and(collect($response->json('data'))->pluck('id'))->toContain($fresh->id)
        ->and(collect($response->json('data'))->pluck('id'))->toContain($oldPopular->id);
});

test('category and seller stubs do not error on for you feed', function () {
    Video::factory()->count(2)->published()->create();

    test()->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonPath('success', true);
});
