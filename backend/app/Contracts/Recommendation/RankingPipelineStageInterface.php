<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankingContext;

interface RankingPipelineStageInterface
{
    public function handle(RankingContext $context, RankedCollection $items): RankedCollection;
}
