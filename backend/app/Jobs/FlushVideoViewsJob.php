<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Drains Redis pending view counters into videos.view_count.
 *
 * Claims counts atomically (GET + DEL) so concurrent recordView increments
 * after a claim are never deleted, and two workers cannot double-apply the same batch.
 */
class FlushVideoViewsJob implements ShouldQueue
{
    use Queueable;

    private const INDEX_KEY = 'views:pending:index';

    private const CLAIM_CHUNK = 100;

    /**
     * Atomic GET then DEL — works on Redis without GETDEL (6.2+).
     */
    private const CLAIM_LUA = <<<'LUA'
local v = redis.call('GET', KEYS[1])
if not v then
  return 0
end
redis.call('DEL', KEYS[1])
return tonumber(v) or 0
LUA;

    public function handle(): void
    {
        /** @var list<string|int> $videoIds */
        $videoIds = Redis::smembers(self::INDEX_KEY);

        if ($videoIds === []) {
            return;
        }

        /** @var array<string, int> $claims */
        $claims = [];

        foreach (array_chunk($videoIds, self::CLAIM_CHUNK) as $chunk) {
            foreach ($chunk as $videoId) {
                $id = (string) $videoId;
                $pending = $this->claimPendingViews($id);

                if ($pending <= 0) {
                    // Empty / already claimed elsewhere — drop from index.
                    // Do not SREM after a successful claim: new INCR+SADD may have landed.
                    Redis::srem(self::INDEX_KEY, $id);

                    continue;
                }

                $claims[$id] = $pending;
            }
        }

        if ($claims === []) {
            return;
        }

        DB::transaction(function () use ($claims): void {
            foreach ($claims as $videoId => $pending) {
                Video::query()
                    ->whereKey($videoId)
                    ->increment('view_count', $pending);
            }
        });
    }

    private function claimPendingViews(string $videoId): int
    {
        $pendingKey = "views:pending:{$videoId}";

        /** @var int|string|null $result */
        $result = Redis::eval(self::CLAIM_LUA, 1, $pendingKey);

        return (int) $result;
    }
}
