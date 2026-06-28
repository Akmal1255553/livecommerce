<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

interface CandidateSourceInterface
{
    public function sourceId(): string;

    public function generate(FeedContext $context): CandidateCollection;
}
