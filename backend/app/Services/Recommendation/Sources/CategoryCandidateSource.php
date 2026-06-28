<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use Illuminate\Support\Facades\Log;

class CategoryCandidateSource implements CandidateSourceInterface
{
    private static bool $logged = false;

    public function sourceId(): string
    {
        return 'category';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        if (! self::$logged) {
            Log::debug('CategoryCandidateSource stub invoked — returns empty until Sprint 4');
            self::$logged = true;
        }

        return CandidateCollection::empty();
    }
}
