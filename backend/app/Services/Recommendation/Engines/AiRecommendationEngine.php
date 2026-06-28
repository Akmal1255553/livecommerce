<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Engines;

use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Recommendation\DTOs\RankedCollection;
use LogicException;

class AiRecommendationEngine implements RecommendationEngineInterface
{
    public function rank(FeedContext $context, CandidateCollection $candidates): RankedCollection
    {
        throw new LogicException('AiRecommendationEngine is not available until Sprint 9.');
    }
}
