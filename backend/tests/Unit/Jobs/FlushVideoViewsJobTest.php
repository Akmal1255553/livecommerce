<?php

declare(strict_types=1);

use App\Jobs\FlushVideoViewsJob;
use Illuminate\Support\Facades\Redis;

test('flush claims pending views atomically and increments in one transaction', function () {
    $video = publishedVideo();
    $videoId = (string) $video->id;

    Redis::shouldReceive('smembers')
        ->once()
        ->with('views:pending:index')
        ->andReturn([$videoId]);

    Redis::shouldReceive('eval')
        ->once()
        ->withArgs(fn ($script, $numKeys, $key): bool => $numKeys === 1 && $key === "views:pending:{$videoId}")
        ->andReturn(7);

    // Successful claim must not SREM — concurrent INCR may have re-added the key.
    Redis::shouldReceive('srem')->never();

    (new FlushVideoViewsJob)->handle();

    expect($video->fresh()->view_count)->toBe(7);
});

test('flush removes empty index entries without writing the database', function () {
    $video = publishedVideo();
    $videoId = (string) $video->id;
    $before = $video->view_count;

    Redis::shouldReceive('smembers')
        ->once()
        ->with('views:pending:index')
        ->andReturn([$videoId]);

    Redis::shouldReceive('eval')
        ->once()
        ->andReturn(0);

    Redis::shouldReceive('srem')
        ->once()
        ->with('views:pending:index', $videoId)
        ->andReturn(1);

    (new FlushVideoViewsJob)->handle();

    expect($video->fresh()->view_count)->toBe($before);
});

test('flush batches multiple claimed videos', function () {
    $a = publishedVideo();
    $b = publishedVideo();

    Redis::shouldReceive('smembers')
        ->once()
        ->andReturn([(string) $a->id, (string) $b->id]);

    Redis::shouldReceive('eval')
        ->twice()
        ->andReturn(2, 5);

    Redis::shouldReceive('srem')->never();

    (new FlushVideoViewsJob)->handle();

    expect($a->fresh()->view_count)->toBe(2)
        ->and($b->fresh()->view_count)->toBe(5);
});
