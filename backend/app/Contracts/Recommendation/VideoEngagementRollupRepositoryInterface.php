<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

use App\Models\VideoEngagementRollup;
use Illuminate\Support\Collection;

interface VideoEngagementRollupRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertBatch(array $rows): void;

    /**
     * @return list<string>
     */
    public function topTrendingVideoIds(int $limit, int $windowHours): array;

    /**
     * @param  list<string>  $videoIds
     * @return array<string, array{views: int, likes: int, comments: int, shares: int, completions: int, watch_seconds: int}>
     */
    public function aggregateSignalsForVideos(array $videoIds, int $windowHours): array;

    /**
     * @return Collection<int, VideoEngagementRollup>
     */
    public function findForVideoInWindow(string $videoId, int $windowHours): Collection;
}
