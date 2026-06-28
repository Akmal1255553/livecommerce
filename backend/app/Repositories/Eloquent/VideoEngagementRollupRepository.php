<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Models\VideoEngagementRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends BaseEloquentRepository<VideoEngagementRollup>
 */
class VideoEngagementRollupRepository extends BaseEloquentRepository implements VideoEngagementRollupRepositoryInterface
{
    public function __construct(VideoEngagementRollup $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->model->newQuery()->upsert(
            $rows,
            ['video_id', 'bucket_hour'],
            ['views', 'likes', 'comments', 'shares', 'saves', 'completions', 'skips', 'follow_after_watch', 'watch_seconds'],
        );
    }

    /**
     * @return list<string>
     */
    public function topTrendingVideoIds(int $limit, int $windowHours): array
    {
        $since = Carbon::now()->utc()->subHours($windowHours);

        /** @var Collection<int, object{video_id: string, total_completions: int|string, total_views: int|string, total_likes: int|string, total_comments: int|string, total_shares: int|string}> $rows */
        $rows = $this->model->newQuery()
            ->select('video_id')
            ->selectRaw('COALESCE(SUM(completions), 0) as total_completions')
            ->selectRaw('COALESCE(SUM(views), 0) as total_views')
            ->selectRaw('COALESCE(SUM(likes), 0) as total_likes')
            ->selectRaw('COALESCE(SUM(comments), 0) as total_comments')
            ->selectRaw('COALESCE(SUM(shares), 0) as total_shares')
            ->where('bucket_hour', '>=', $since)
            ->groupBy('video_id')
            ->toBase()
            ->get();

        /** @var list<string> */
        return $rows
            ->map(static function (object $row): array {
                $views = max(1, (int) $row->total_views);

                return [
                    'video_id' => (string) $row->video_id,
                    'score' => ((int) $row->total_completions / $views)
                        + (int) $row->total_likes
                        + (int) $row->total_comments
                        + (int) $row->total_shares,
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('video_id')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $videoIds
     * @return array<string, array{views: int, likes: int, comments: int, shares: int, completions: int, watch_seconds: int}>
     */
    public function aggregateSignalsForVideos(array $videoIds, int $windowHours): array
    {
        if ($videoIds === []) {
            return [];
        }

        $since = Carbon::now()->utc()->subHours($windowHours);

        $rows = $this->model->newQuery()
            ->select('video_id')
            ->selectRaw('COALESCE(SUM(views), 0) as views')
            ->selectRaw('COALESCE(SUM(likes), 0) as likes')
            ->selectRaw('COALESCE(SUM(comments), 0) as comments')
            ->selectRaw('COALESCE(SUM(shares), 0) as shares')
            ->selectRaw('COALESCE(SUM(completions), 0) as completions')
            ->selectRaw('COALESCE(SUM(watch_seconds), 0) as watch_seconds')
            ->whereIn('video_id', $videoIds)
            ->where('bucket_hour', '>=', $since)
            ->groupBy('video_id')
            ->get();

        /** @var array<string, array{views: int, likes: int, comments: int, shares: int, completions: int, watch_seconds: int}> $result */
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->video_id] = [
                'views' => (int) $row->views,
                'likes' => (int) $row->likes,
                'comments' => (int) $row->comments,
                'shares' => (int) $row->shares,
                'completions' => (int) $row->completions,
                'watch_seconds' => (int) $row->watch_seconds,
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, VideoEngagementRollup>
     */
    public function findForVideoInWindow(string $videoId, int $windowHours): Collection
    {
        $since = Carbon::now()->utc()->subHours($windowHours);

        /** @var Collection<int, VideoEngagementRollup> */
        return $this->model->newQuery()
            ->where('video_id', $videoId)
            ->where('bucket_hour', '>=', $since)
            ->orderBy('bucket_hour')
            ->get();
    }
}
