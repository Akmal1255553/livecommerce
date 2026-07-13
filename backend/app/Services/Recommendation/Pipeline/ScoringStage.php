<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Models\LiveSession;
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

        $videoItems = array_values(array_filter($items->items, static fn (RankedItem $i): bool => $i->isVideo()));
        $liveItems = array_values(array_filter($items->items, static fn (RankedItem $i): bool => $i->isLive()));

        $videoIds = array_map(static fn (RankedItem $i): string => $i->videoId, $videoItems);
        $windowHours = (int) config('recommendation.rollup.trending_window_hours', 24);
        $rollupSignals = $videoIds !== []
            ? $this->rollups->aggregateSignalsForVideos($videoIds, $windowHours)
            : [];
        $weights = $this->resolveWeights($context);

        $rawSignals = [];

        foreach ($videoItems as $item) {
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

            $rawSignals[$item->key()] = [
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

        foreach ($videoItems as $item) {
            $norm = $normalized[$item->key()];
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
                type: $item->type,
            );
        }

        $liveBoost = (float) config('recommendation.scoring.live_boost', 1.15);

        foreach ($liveItems as $item) {
            /** @var LiveSession|null $session */
            $session = $context->liveSessions->get($item->videoId);
            $viewers = $session?->viewer_count ?? 0;
            $score = $liveBoost + min(0.5, log(1 + $viewers) / 10);

            $scored[] = new RankedItem(
                videoId: $item->videoId,
                score: $score,
                signals: ['live_boost' => $liveBoost, 'viewers' => $viewers],
                sources: $item->sources,
                type: $item->type,
            );
        }

        usort($scored, function (RankedItem $a, RankedItem $b) use ($context): int {
            $scoreCompare = $b->score <=> $a->score;

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            if ($a->isLive() && ! $b->isLive()) {
                return -1;
            }

            if ($b->isLive() && ! $a->isLive()) {
                return 1;
            }

            /** @var Video|null $videoA */
            $videoA = $a->isVideo() ? $context->videos->get($a->videoId) : null;
            /** @var Video|null $videoB */
            $videoB = $b->isVideo() ? $context->videos->get($b->videoId) : null;

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
        if ($raw === []) {
            return [];
        }

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

        foreach ($raw as $contentKey => $signals) {
            $norm = ['freshness' => $signals['freshness']];

            foreach ($keys as $key) {
                $range = $maxs[$key] - $mins[$key];
                $norm[$key] = $range > 0
                    ? ($signals[$key] - $mins[$key]) / $range
                    : 0.0;
            }

            $normalized[$contentKey] = $norm;
        }

        return $normalized;
    }
}
