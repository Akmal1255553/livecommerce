<?php

declare(strict_types=1);

use App\Enums\FeedStrategy;
use App\Models\User;
use App\Models\Video;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;
use App\Services\Recommendation\Pipeline\ExplorationStage;

test('exploration stage injects explore candidates at configured slots', function () {
    config(['recommendation.exploration.slot_positions' => [2]]);

    $creator = User::factory()->create();
    $exploitVideos = collect([
        Video::factory()->for($creator)->published()->create(),
        Video::factory()->for($creator)->published()->create(),
        Video::factory()->for($creator)->published()->create(),
    ])->keyBy('id');

    $exploreVideo = Video::factory()->for($creator)->published()->create(['view_count' => 5]);

    $items = new RankedCollection([
        new RankedItem($exploitVideos->values()[0]->id, score: 1.0),
        new RankedItem($exploitVideos->values()[1]->id, score: 0.9),
        new RankedItem($exploitVideos->values()[2]->id, score: 0.8),
    ]);

    $context = new RankingContext(
        feedContext: new FeedContext(strategy: FeedStrategy::ForYou),
        exclusionVideoIds: [],
        videos: $exploitVideos->merge([$exploreVideo->id => $exploreVideo])->keyBy('id'),
        explorationCandidates: new CandidateCollection([
            new Candidate($exploreVideo->id, ['exploration']),
        ]),
    );

    $result = app(ExplorationStage::class)->handle($context, $items);
    $ids = array_map(static fn (RankedItem $item): string => $item->videoId, $result->items);

    expect($ids[1])->toBe($exploreVideo->id);
});

test('exploration stage preserves exploit order outside slots', function () {
    config(['recommendation.exploration.slot_positions' => [99]]);

    $creator = User::factory()->create();
    $first = Video::factory()->for($creator)->published()->create();
    $second = Video::factory()->for($creator)->published()->create();

    $items = new RankedCollection([
        new RankedItem($first->id, score: 1.0),
        new RankedItem($second->id, score: 0.5),
    ]);

    $context = new RankingContext(
        feedContext: new FeedContext(strategy: FeedStrategy::ForYou),
        exclusionVideoIds: [],
        videos: collect([$first, $second])->keyBy('id'),
        explorationCandidates: CandidateCollection::empty(),
    );

    $result = app(ExplorationStage::class)->handle($context, $items);

    expect($result->items[0]->videoId)->toBe($first->id)
        ->and($result->items[1]->videoId)->toBe($second->id);
});
