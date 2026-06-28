<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;

class ExplorationStage implements RankingPipelineStageInterface
{
    public function handle(RankingContext $context, RankedCollection $items): RankedCollection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $slotPositions = config('recommendation.exploration.slot_positions', [3, 8, 15]);
        if (! is_array($slotPositions)) {
            $slotPositions = [3, 8, 15];
        }

        $exploit = $items->items;
        $explorePool = $this->buildExplorePool($context);
        $result = [];
        /** @var array<string, true> $placedIds */
        $placedIds = [];
        $exploitIndex = 0;
        $exploreIndex = 0;
        $position = 1;

        while ($exploitIndex < count($exploit) || $exploreIndex < count($explorePool)) {
            if (in_array($position, $slotPositions, true) && $this->placeNext($explorePool, $exploreIndex, $placedIds, $result)) {
                $position++;

                continue;
            }

            if ($this->placeNext($exploit, $exploitIndex, $placedIds, $result)) {
                $position++;

                continue;
            }

            break;
        }

        return new RankedCollection(items: $result, snapshot: $items->snapshot);
    }

    /**
     * @param  list<RankedItem>  $pool
     * @param  array<string, true>  $placedIds
     * @param  list<RankedItem>  $result
     */
    private function placeNext(array $pool, int &$index, array &$placedIds, array &$result): bool
    {
        while ($index < count($pool)) {
            $item = $pool[$index];
            $index++;

            if (isset($placedIds[$item->videoId])) {
                continue;
            }

            $result[] = $item;
            $placedIds[$item->videoId] = true;

            return true;
        }

        return false;
    }

    /**
     * @return list<RankedItem>
     */
    private function buildExplorePool(RankingContext $context): array
    {
        $pool = [];

        foreach ($context->explorationCandidates->items as $candidate) {
            $pool[] = new RankedItem(
                videoId: $candidate->videoId,
                score: 0.0,
                signals: ['exploration' => 1.0],
                sources: $candidate->sources,
            );
        }

        return $pool;
    }
}
