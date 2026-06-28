<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use Illuminate\Support\Collection;

class CandidateGeneratorService
{
    /**
     * @param  Collection<int, CandidateSourceInterface>  $sources
     */
    public function __construct(
        private readonly Collection $sources,
    ) {}

    public function generate(FeedContext $context): CandidateCollection
    {
        $sourceIds = $context->strategy->candidateSourceIds();
        /** @var array<string, Candidate> $merged */
        $merged = [];

        foreach ($this->sources as $source) {
            if ($source->sourceId() === 'previously_watched') {
                continue;
            }

            if (! in_array($source->sourceId(), $sourceIds, true)) {
                continue;
            }

            foreach ($source->generate($context)->items as $candidate) {
                if (isset($merged[$candidate->videoId])) {
                    $merged[$candidate->videoId] = $merged[$candidate->videoId]->withSources($candidate->sources);
                } else {
                    $merged[$candidate->videoId] = $candidate;
                }
            }
        }

        return new CandidateCollection(array_values($merged));
    }

    /**
     * @return list<string>
     */
    public function exclusionVideoIds(FeedContext $context): array
    {
        foreach ($this->sources as $source) {
            if ($source->sourceId() !== 'previously_watched') {
                continue;
            }

            return $source->generate($context)->videoIds();
        }

        return [];
    }

    public function explorationCandidates(FeedContext $context): CandidateCollection
    {
        foreach ($this->sources as $source) {
            if ($source->sourceId() !== 'exploration') {
                continue;
            }

            return $source->generate($context);
        }

        return CandidateCollection::empty();
    }
}
