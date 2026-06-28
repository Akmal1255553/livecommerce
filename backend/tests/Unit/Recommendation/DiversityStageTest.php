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
use App\Services\Recommendation\Pipeline\DiversityStage;

test('diversity stage enforces max consecutive same author', function () {
    config(['recommendation.diversity.max_consecutive_same_author' => 2]);

    $authorA = User::factory()->create();
    $authorB = User::factory()->create();

    $videos = collect([
        Video::factory()->for($authorA)->published()->create(['like_count' => 100]),
        Video::factory()->for($authorA)->published()->create(['like_count' => 90]),
        Video::factory()->for($authorA)->published()->create(['like_count' => 80]),
        Video::factory()->for($authorB)->published()->create(['like_count' => 70]),
    ])->keyBy('id');

    $items = new RankedCollection([
        new RankedItem($videos->values()[0]->id, score: 1.0),
        new RankedItem($videos->values()[1]->id, score: 0.9),
        new RankedItem($videos->values()[2]->id, score: 0.8),
        new RankedItem($videos->values()[3]->id, score: 0.7),
    ]);

    $context = new RankingContext(
        feedContext: new FeedContext(strategy: FeedStrategy::ForYou),
        exclusionVideoIds: [],
        videos: $videos,
        explorationCandidates: CandidateCollection::empty(),
    );

    $result = app(DiversityStage::class)->handle($context, $items);
    $authorIds = array_map(
        static fn (RankedItem $item): string => (string) $videos->get($item->videoId)?->user_id,
        $result->items,
    );

    $streak = 1;
    $maxStreak = 1;

    for ($i = 1, $count = count($authorIds); $i < $count; $i++) {
        if ($authorIds[$i] === $authorIds[$i - 1]) {
            $streak++;
            $maxStreak = max($maxStreak, $streak);
        } else {
            $streak = 1;
        }
    }

    expect($maxStreak)->toBeLessThanOrEqual(2);
});
