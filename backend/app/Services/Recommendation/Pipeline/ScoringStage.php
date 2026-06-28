<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Models\Video;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;
use Illuminate\Support\Carbon;

class ScoringStage implements RankingPipelineStageInterface
{
    public function __construct(
        private readonly VideoEngagementRollupRepositoryInterface $rollups,
    ) {}

    public function handle(RankingContext $context, RankedCollection $items): RankedCollection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $videoIds = $items->videoIds();
        $windowHours = (int) config('recommendation.rollup.trending_window_hours', 24);
        $rollupSignals = $this->rollups->aggregateSignalsForVideos($videoIds, $windowHours);
        $weights = $this->resolveWeights($context);

        $rawSignals = [];

        foreach ($items->items as $item) {
            /** @var Video|null $video */
            $video = $context->videos->get($item->videoId);
            $rollup = $rollupSignals[$item->videoId] ?? null;

            $viewCount = $video !== null ? $video->view_count : 0;
            $views = max(1, $rollup['views'] ?? $viewCount);
            $completions = $rollup['completions'] ?? 0;
            $watchSeconds = $rollup['watch_seconds'] ?? 0;
            $likes = $rollup['likes'] ?? ($video !== null ? $video->like_count : 0);
            $comments = $rollup['comments'] ?? ($video !== null ? $video->comment_count : 0);
            $shares = $rollup['shares'] ?? ($video !== null ? $video->share_count : 0);

            $publishedAt = $video !== null
                ? ($video->published_at ?? $video->created_at ?? Carbon::now())
                : Carbon::now();
            $hoursSincePublish = max(0, $publishedAt->diffInHours(Carbon::now()));
            $freshness = 1 / (1 + ($hoursSincePublish / 24));

            $rawSignals[$item->videoId] = [
                'completion' => $completions / $views,
                'watch_time' => log(1 + $watchSeconds),
                'like' => (float) $likes,
                'comment' => (float) $comments,
                'share' => (float) $shares,
                'freshness' => $freshness,
            ];
        }

        $normalized = $this->normalizeBatch($rawSignals);
        $scored = [];

        foreach ($items->items as $item) {
            $norm = $normalized[$item->videoId];
            $score =
                $norm['completion'] * $weights['completion']
                + $norm['watch_time'] * $weights['watch_time']
                + $norm['like'] * $weights['like']
                + $norm['comment'] * $weights['comment']
                + $norm['share'] * $weights['share']
                + $norm['freshness'] * $weights['freshness'];

            $scored[] = new RankedItem(
                videoId: $item->videoId,
                score: $score,
                signals: $norm,
                sources: $item->sources,
            );
        }

        usort($scored, function (RankedItem $a, RankedItem $b) use ($context): int {
            $scoreCompare = $b->score <=> $a->score;

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            /** @var Video|null $videoA */
            $videoA = $context->videos->get($a->videoId);
            /** @var Video|null $videoB */
            $videoB = $context->videos->get($b->videoId);

            $publishedA = $videoA !== null
                ? ($videoA->published_at ?? $videoA->created_at ?? Carbon::createFromTimestamp(0))
                : Carbon::createFromTimestamp(0);
            $publishedB = $videoB !== null
                ? ($videoB->published_at ?? $videoB->created_at ?? Carbon::createFromTimestamp(0))
                : Carbon::createFromTimestamp(0);

            $publishedCompare = $publishedB <=> $publishedA;

            if ($publishedCompare !== 0) {
                return $publishedCompare;
            }

            return $b->videoId <=> $a->videoId;
        });

        return new RankedCollection(items: $scored, snapshot: $items->snapshot);
    }

    /**
     * @return array{completion: float, watch_time: float, like: float, comment: float, share: float, freshness: float}
     */
    private function resolveWeights(RankingContext $context): array
    {
        $feedKey = $context->feedContext->strategy->value;
        $feedOverrides = config("recommendation.scoring.feeds.{$feedKey}", []);

        if (! is_array($feedOverrides) || $feedOverrides === []) {
            /** @var array{completion: float, watch_time: float, like: float, comment: float, share: float, freshness: float} */
            return config('recommendation.scoring.weights');
        }

        /** @var array{completion: float, watch_time: float, like: float, comment: float, share: float, freshness: float} */
        return array_merge(config('recommendation.scoring.weights'), $feedOverrides);
    }

    /**
     * @param  array<string, array{completion: float, watch_time: float, like: float, comment: float, share: float, freshness: float}>  $raw
     * @return array<string, array{completion: float, watch_time: float, like: float, comment: float, share: float, freshness: float}>
     */
    private function normalizeBatch(array $raw): array
    {
        $keys = ['completion', 'watch_time', 'like', 'comment', 'share'];
        $mins = array_fill_keys($keys, PHP_FLOAT_MAX);
        $maxs = array_fill_keys($keys, PHP_FLOAT_MIN);

        foreach ($raw as $signals) {
            foreach ($keys as $key) {
                $mins[$key] = min($mins[$key], $signals[$key]);
                $maxs[$key] = max($maxs[$key], $signals[$key]);
            }
        }

        $normalized = [];

        foreach ($raw as $videoId => $signals) {
            $norm = ['freshness' => $signals['freshness']];

            foreach ($keys as $key) {
                $range = $maxs[$key] - $mins[$key];
                $norm[$key] = $range > 0
                    ? ($signals[$key] - $mins[$key]) / $range
                    : 0.0;
            }

            $normalized[$videoId] = $norm;
        }

        return $normalized;
    }
}
