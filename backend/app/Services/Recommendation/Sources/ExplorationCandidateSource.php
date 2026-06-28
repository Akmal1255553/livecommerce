<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class ExplorationCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly VideoRepositoryInterface $videos,
    ) {}

    public function sourceId(): string
    {
        return 'exploration';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        $limit = (int) config('recommendation.cache.snapshot_size', 500);
        $candidates = $this->videos->listExplorationCandidates($limit);

        $items = $candidates->map(
            static fn ($video): Candidate => new Candidate($video->id, ['exploration']),
        )->all();

        return new CandidateCollection($items);
    }
}
