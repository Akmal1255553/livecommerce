<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class TrendingCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly VideoEngagementRollupRepositoryInterface $rollups,
        private readonly VideoRepositoryInterface $videos,
    ) {}

    public function sourceId(): string
    {
        return 'trending';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        $limit = (int) config('recommendation.cache.snapshot_size', 500);
        $windowHours = (int) config('recommendation.rollup.trending_window_hours', 24);
        $videoIds = $this->rollups->topTrendingVideoIds($limit, $windowHours);

        if ($videoIds === []) {
            $videoIds = $this->videos->listNewCandidates($limit)
                ->pluck('id')
                ->all();
        }

        $items = array_map(
            static fn (string $videoId): Candidate => new Candidate($videoId, ['trending']),
            $videoIds,
        );

        return new CandidateCollection($items);
    }
}
