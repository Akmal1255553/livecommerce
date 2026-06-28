<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Enums\FeedStrategy;
use App\Services\Recommendation\CandidateGeneratorService;
use App\Services\Recommendation\DTOs\FeedContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshTrendingCacheJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        RecommendationEngineInterface $engine,
        CandidateGeneratorService $candidateGenerator,
    ): void {
        $context = new FeedContext(
            strategy: FeedStrategy::Trending,
            limit: (int) config('recommendation.cache.snapshot_size', 500),
        );

        $candidates = $candidateGenerator->generate($context);
        $ranked = $engine->rank($context, $candidates);
        $ids = $ranked->videoIds();
        $version = $ranked->snapshot ?? sha1(implode(',', $ids));

        $snapshot = ['version' => $version, 'ids' => $ids];
        $ttl = (int) config('recommendation.cache.trending_ttl', 300);

        Cache::put('feed:trending:snapshot', $snapshot, $ttl);
    }
}
