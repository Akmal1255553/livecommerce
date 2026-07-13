<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;

class FinalRankingStage implements RankingPipelineStageInterface
{
    public function handle(RankingContext $context, RankedCollection $items): RankedCollection
    {
        $keys = array_map(static fn (RankedItem $item): string => $item->key(), $items->items);
        $snapshot = sha1(implode(',', $keys));

        return $items->withSnapshot($snapshot);
    }
}
