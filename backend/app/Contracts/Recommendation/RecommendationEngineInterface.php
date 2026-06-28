<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Recommendation\DTOs\RankedCollection;

interface RecommendationEngineInterface
{
    public function rank(FeedContext $context, CandidateCollection $candidates): RankedCollection;
}
