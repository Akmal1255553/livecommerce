<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Recommendation\EngagementEventRepositoryInterface;
use App\Enums\EngagementEventType;
use App\Models\EngagementEvent;
use Illuminate\Support\Carbon;

/**
 * @extends BaseEloquentRepository<EngagementEvent>
 */
class EngagementEventRepository extends BaseEloquentRepository implements EngagementEventRepositoryInterface
{
    public function __construct(EngagementEvent $model)
    {
        parent::__construct($model);
    }

    /**
     * @return list<string>
     */
    public function completedVideoIdsForUser(string $userId, int $days): array
    {
        $since = Carbon::now()->subDays($days);

        /** @var list<string> */
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('event_type', EngagementEventType::VideoProgress100)
            ->where('created_at', '>=', $since)
            ->whereNotNull('video_id')
            ->distinct()
            ->pluck('video_id')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function skippedVideoIdsForUser(string $userId, int $days, int $maxWatchedSeconds): array
    {
        $since = Carbon::now()->subDays($days);

        /** @var list<string> */
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('event_type', EngagementEventType::Skip)
            ->where('created_at', '>=', $since)
            ->whereNotNull('video_id')
            ->get()
            ->filter(function (EngagementEvent $event) use ($maxWatchedSeconds): bool {
                $payload = $event->payload ?? [];
                $seconds = $payload['seconds'] ?? $payload['watched_seconds'] ?? 0;

                return (int) $seconds <= $maxWatchedSeconds;
            })
            ->pluck('video_id')
            ->unique()
            ->values()
            ->all();
    }
}
