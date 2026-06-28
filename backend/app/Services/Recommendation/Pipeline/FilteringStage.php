<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Video;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankingContext;

class FilteringStage implements RankingPipelineStageInterface
{
    public function handle(RankingContext $context, RankedCollection $items): RankedCollection
    {
        $exclusions = array_flip($context->exclusionVideoIds);
        $filtered = [];

        foreach ($items->items as $item) {
            if (isset($exclusions[$item->videoId])) {
                continue;
            }

            /** @var Video|null $video */
            $video = $context->videos->get($item->videoId);

            if ($video === null) {
                continue;
            }

            if ($video->status !== VideoStatus::Published) {
                continue;
            }

            if ($video->visibility !== VideoVisibility::Public) {
                continue;
            }

            if ($video->trashed()) {
                continue;
            }

            $filtered[] = $item;
        }

        return new RankedCollection(items: $filtered, snapshot: $items->snapshot);
    }
}
