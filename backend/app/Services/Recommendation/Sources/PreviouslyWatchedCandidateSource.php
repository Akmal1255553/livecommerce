<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Recommendation\EngagementEventRepositoryInterface;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class PreviouslyWatchedCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly EngagementEventRepositoryInterface $engagementEvents,
    ) {}

    public function sourceId(): string
    {
        return 'previously_watched';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        if ($context->user === null) {
            return CandidateCollection::empty();
        }

        $completedDays = (int) config('recommendation.filtering.completed_exclude_days', 30);
        $skipDays = (int) config('recommendation.filtering.skip_exclude_days', 7);
        $maxSkipSeconds = (int) config('recommendation.filtering.skip_max_watched_seconds', 3);

        $completed = $this->engagementEvents->completedVideoIdsForUser($context->user->id, $completedDays);
        $skipped = $this->engagementEvents->skippedVideoIdsForUser($context->user->id, $skipDays, $maxSkipSeconds);
        $videoIds = array_values(array_unique([...$completed, ...$skipped]));

        $items = array_map(
            static fn (string $videoId): Candidate => new Candidate($videoId, ['previously_watched']),
            $videoIds,
        );

        return new CandidateCollection($items);
    }
}
