<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Pipeline;

use App\Contracts\Recommendation\RankingPipelineStageInterface;
use App\Models\LiveSession;
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
                $authorId = $this->authorId($context, $item);

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
                    $authorId = $this->authorId($context, $item);

                    if ($authorId !== $lastAuthor) {
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

            $authorId = $this->authorId($context, $picked);

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

    private function authorId(RankingContext $context, RankedItem $item): ?string
    {
        if ($item->isLive()) {
            /** @var LiveSession|null $session */
            $session = $context->liveSessions->get($item->videoId);

            return $session?->seller_id;
        }

        /** @var Video|null $video */
        $video = $context->videos->get($item->videoId);

        return $video?->user_id;
    }
}
