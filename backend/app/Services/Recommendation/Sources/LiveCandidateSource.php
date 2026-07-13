<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Repositories\LiveSessionRepositoryInterface;
use App\Enums\ContentType;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class LiveCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly LiveSessionRepositoryInterface $sessions,
    ) {}

    public function sourceId(): string
    {
        return 'live';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        $limit = min($context->limit, 50);
        $items = [];

        foreach ($this->sessions->listLive($limit) as $session) {
            $items[] = new Candidate(
                videoId: (string) $session->id,
                sources: ['live'],
                type: ContentType::Live,
            );
        }

        return new CandidateCollection($items);
    }
}
