<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;
use App\Models\Video;
use Illuminate\Support\Collection;

readonly class RankingContext extends DataTransferObject
{
    /**
     * @param  list<string>  $exclusionVideoIds
     * @param  Collection<int, Video>  $videos
     */
    public function __construct(
        public FeedContext $feedContext,
        public array $exclusionVideoIds,
        public Collection $videos,
        public CandidateCollection $explorationCandidates,
    ) {}
}
