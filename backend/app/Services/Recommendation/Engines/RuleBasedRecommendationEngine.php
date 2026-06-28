<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Engines;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Services\Recommendation\CandidateGeneratorService;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;

class RuleBasedRecommendationEngine implements RecommendationEngineInterface
{
    /**
     * @param  list<RankingPipelineStageInterface>  $stages
     */
    public function __construct(
        private readonly CandidateGeneratorService $candidateGenerator,
        private readonly VideoRepositoryInterface $videos,
        private readonly array $stages,
    ) {}

    public function rank(FeedContext $context, CandidateCollection $candidates): RankedCollection
    {
        if ($candidates->isEmpty()) {
            return RankedCollection::empty();
        }

        $exclusionIds = $this->candidateGenerator->exclusionVideoIds($context);
        $explorationCandidates = $this->candidateGenerator->explorationCandidates($context);
        $videoModels = $this->videos->findPublishedByIds($candidates->videoIds())->keyBy('id');

        $rankingContext = new RankingContext(
            feedContext: $context,
            exclusionVideoIds: $exclusionIds,
            videos: $videoModels,
            explorationCandidates: $explorationCandidates,
        );

        $items = array_map(
            static fn ($candidate): RankedItem => new RankedItem(
                videoId: $candidate->videoId,
                score: 0.0,
                sources: $candidate->sources,
            ),
            $candidates->items,
        );

        $collection = new RankedCollection(items: $items);

        foreach ($this->stages as $stage) {
            $collection = $stage->handle($rankingContext, $collection);
        }

        return $collection;
    }
}
