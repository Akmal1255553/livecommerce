<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Models\Video;
use App\Services\Recommendation\DTOs\RankedCollection;
use App\Services\Recommendation\DTOs\RankedItem;
use App\Services\Recommendation\DTOs\RankingContext;

class DiversityStage implements RankingPipelineStageInterface
{
    public function handle(RankingContext $context, RankedCollection $items): RankedCollection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $maxAuthor = (int) config('recommendation.diversity.max_consecutive_same_author', 2);

        $sorted = $items->items;
        usort($sorted, static fn (RankedItem $a, RankedItem $b): int => $b->score <=> $a->score);

        $result = [];
        $remaining = $sorted;
        $authorStreak = 0;
        $lastAuthor = null;

        while ($remaining !== []) {
            $picked = null;
            $pickedIndex = null;

            foreach ($remaining as $index => $item) {
                /** @var Video|null $video */
                $video = $context->videos->get($item->videoId);
                $authorId = $video?->user_id;

                $authorOk = $authorId === null
                    || $authorId !== $lastAuthor
                    || $authorStreak < $maxAuthor;

                if ($authorOk) {
                    $picked = $item;
                    $pickedIndex = $index;
                    break;
                }
            }

            if ($picked === null) {
                foreach ($remaining as $index => $item) {
                    /** @var Video|null $video */
                    $video = $context->videos->get($item->videoId);

                    if ($video?->user_id !== $lastAuthor) {
                        $picked = $item;
                        $pickedIndex = $index;
                        break;
                    }
                }
            }

            if ($picked === null) {
                $picked = array_shift($remaining);
            } else {
                array_splice($remaining, $pickedIndex, 1);
            }

            /** @var Video|null $video */
            $video = $context->videos->get($picked->videoId);
            $authorId = $video?->user_id;

            if ($authorId === $lastAuthor) {
                $authorStreak++;
            } else {
                $authorStreak = 1;
                $lastAuthor = $authorId;
            }

            $result[] = $picked;
        }

        return new RankedCollection(items: $result, snapshot: $items->snapshot);
    }
}
