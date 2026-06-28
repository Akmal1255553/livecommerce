<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Enums\EngagementEventType;
use App\Models\EngagementEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AggregateEngagementRollupsJob implements ShouldQueue
{
    use Queueable;

    public function handle(VideoEngagementRollupRepositoryInterface $rollups): void
    {
        $intervalMinutes = (int) config('recommendation.rollup.aggregation_interval_minutes', 15);
        $since = Carbon::now()->utc()->subMinutes($intervalMinutes);

        $events = EngagementEvent::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('video_id')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        /** @var array<string, array<string, mixed>> $aggregated */
        $aggregated = [];

        foreach ($events as $event) {
            $bucketHour = $event->created_at->utc()->startOfHour()->toDateTimeString();
            $key = "{$event->video_id}|{$bucketHour}";

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'video_id' => $event->video_id,
                    'bucket_hour' => $bucketHour,
                    'views' => 0,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'saves' => 0,
                    'completions' => 0,
                    'skips' => 0,
                    'follow_after_watch' => 0,
                    'watch_seconds' => 0,
                ];
            }

            match ($event->event_type) {
                EngagementEventType::View => $aggregated[$key]['views']++,
                EngagementEventType::Like => $aggregated[$key]['likes']++,
                EngagementEventType::Comment => $aggregated[$key]['comments']++,
                EngagementEventType::Share => $aggregated[$key]['shares']++,
                EngagementEventType::Save => $aggregated[$key]['saves']++,
                EngagementEventType::VideoProgress100 => $aggregated[$key]['completions']++,
                EngagementEventType::Skip => $aggregated[$key]['skips']++,
                EngagementEventType::FollowAfterWatch => $aggregated[$key]['follow_after_watch']++,
                EngagementEventType::WatchTime => $aggregated[$key]['watch_seconds'] += $this->watchSecondsFromPayload($event->payload),
                default => null,
            };
        }

        $rows = array_values($aggregated);

        DB::transaction(static function () use ($rollups, $rows): void {
            $rollups->upsertBatch($rows);
        });
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function watchSecondsFromPayload(?array $payload): int
    {
        if ($payload === null) {
            return 0;
        }

        $seconds = $payload['seconds'] ?? $payload['watched_seconds'] ?? 0;

        return max(0, (int) $seconds);
    }
}
