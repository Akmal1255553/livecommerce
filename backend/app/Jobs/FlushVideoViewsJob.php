<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class FlushVideoViewsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $videoIds = Redis::smembers('views:pending:index');

        foreach ($videoIds as $videoId) {
            $pendingKey = "views:pending:{$videoId}";
            $pending = (int) Redis::get($pendingKey);

            if ($pending <= 0) {
                Redis::srem('views:pending:index', $videoId);

                continue;
            }

            DB::transaction(function () use ($videoId, $pending): void {
                $video = Video::query()
                    ->whereKey($videoId)
                    ->lockForUpdate()
                    ->first();

                if ($video !== null) {
                    $video->increment('view_count', $pending);
                }
            });

            Redis::del($pendingKey);
            Redis::srem('views:pending:index', $videoId);
        }
    }
}
