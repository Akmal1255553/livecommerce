<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Contracts\Repositories\BookmarkRepositoryInterface;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class BookmarksCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly BookmarkRepositoryInterface $bookmarks,
    ) {}

    public function sourceId(): string
    {
        return 'bookmarks';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        if ($context->user === null) {
            return CandidateCollection::empty();
        }

        $bookmarks = $this->bookmarks->cursorPaginateForUser($context->user->id, null, 100);
        $items = [];

        foreach ($bookmarks as $bookmark) {
            $items[] = new Candidate($bookmark->video_id, ['bookmarks']);
        }

        return new CandidateCollection($items);
    }
}
