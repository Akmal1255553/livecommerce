<?php

declare(strict_types=1);

use App\Enums\FeedStrategy;
use App\Models\User;
use App\Models\Video;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;
use App\Services\Recommendation\Pipeline\ScoringStage;

test('scoring stage applies config weights', function () {
    config([
        'recommendation.scoring.weights' => [
            'completion' => 0.40,
            'watch_time' => 0.20,
            'like' => 0.15,
            'comment' => 0.10,
            'share' => 0.10,
            'freshness' => 0.05,
        ],
    ]);

    $creator = User::factory()->create();
    $videoA = Video::factory()->for($creator)->published()->create(['like_count' => 100]);
    $videoB = Video::factory()->for($creator)->published()->create(['like_count' => 0]);

    $context = new RankingContext(
        feedContext: new FeedContext(strategy: FeedStrategy::ForYou),
        exclusionVideoIds: [],
        videos: collect([$videoA, $videoB])->keyBy('id'),
        explorationCandidates: CandidateCollection::empty(),
    );

    $items = new RankedCollection([
        new RankedItem($videoA->id),
        new RankedItem($videoB->id),
    ]);

    $stage = app(ScoringStage::class);
    $result = $stage->handle($context, $items);

    expect($result->items[0]->score)->toBeGreaterThan($result->items[1]->score);
});

test('scoring stage normalizes signals within batch', function () {
    $creator = User::factory()->create();
    $videoA = Video::factory()->for($creator)->published()->create(['like_count' => 100]);
    $videoB = Video::factory()->for($creator)->published()->create(['like_count' => 0]);

    $context = new RankingContext(
        feedContext: new FeedContext(strategy: FeedStrategy::Trending),
        exclusionVideoIds: [],
        videos: collect([$videoA, $videoB])->keyBy('id'),
        explorationCandidates: CandidateCollection::empty(),
    );

    $items = new RankedCollection([
        new RankedItem($videoA->id),
        new RankedItem($videoB->id),
    ]);

    $result = app(ScoringStage::class)->handle($context, $items);

    expect($result->items[0]->signals['like'])->toBe(1.0)
        ->and($result->items[1]->signals['like'])->toBe(0.0);
});
