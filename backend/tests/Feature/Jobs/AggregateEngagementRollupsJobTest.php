<?php

declare(strict_types=1);

use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Enums\EngagementEventType;
use App\Jobs\AggregateEngagementRollupsJob;
use App\Models\EngagementEvent;
use App\Models\VideoEngagementRollup;
use Illuminate\Support\Str;

test('aggregate engagement rollups job maps like and watch time', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();
    $eventTime = now();

    EngagementEvent::query()->create([
        'event_type' => EngagementEventType::Like,
        'user_id' => null,
        'video_id' => $video->id,
        'session_id' => $sessionId,
        'payload' => null,
        'created_at' => $eventTime,
    ]);

    EngagementEvent::query()->create([
        'event_type' => EngagementEventType::WatchTime,
        'user_id' => null,
        'video_id' => $video->id,
        'session_id' => $sessionId,
        'payload' => ['seconds' => 42],
        'created_at' => $eventTime,
    ]);

    (new AggregateEngagementRollupsJob)->handle(app(VideoEngagementRollupRepositoryInterface::class));

    $bucketHour = $eventTime->copy()->utc()->startOfHour();

    $rollup = VideoEngagementRollup::query()
        ->where('video_id', $video->id)
        ->whereDate('bucket_hour', $bucketHour->toDateString())
        ->first();

    expect($rollup)->not->toBeNull()
        ->and($rollup->likes)->toBe(1)
        ->and($rollup->watch_seconds)->toBe(42);
});

test('aggregate engagement rollups job is idempotent on re-run', function () {
    $video = publishedVideo();
    $sessionId = (string) Str::uuid();
    $createdAt = now();

    EngagementEvent::query()->create([
        'event_type' => EngagementEventType::View,
        'user_id' => null,
        'video_id' => $video->id,
        'session_id' => $sessionId,
        'payload' => null,
        'created_at' => $createdAt,
    ]);

    $job = new AggregateEngagementRollupsJob;
    $repo = app(VideoEngagementRollupRepositoryInterface::class);

    $job->handle($repo);
    $job->handle($repo);

    expect(VideoEngagementRollup::query()->where('video_id', $video->id)->count())->toBe(1)
        ->and(VideoEngagementRollup::query()->where('video_id', $video->id)->value('views'))->toBe(1);
});
