<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;
use Illuminate\Support\Facades\Log;

class SellerCandidateSource implements CandidateSourceInterface
{
    private static bool $logged = false;

    public function sourceId(): string
    {
        return 'seller';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        if (! self::$logged) {
            Log::debug('SellerCandidateSource stub invoked — returns empty until Sprint 5');
            self::$logged = true;
        }

        return CandidateCollection::empty();
    }
}
