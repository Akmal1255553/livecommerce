<?php

declare(strict_types=1);

namespace App\Services\Recommendation\Sources;

use App\Contracts\Recommendation\CandidateSourceInterface;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Video;
use App\Services\Recommendation\DTOs\Candidate;
use App\Services\Recommendation\DTOs\CandidateCollection;
use App\Services\Recommendation\DTOs\FeedContext;

class PopularCandidateSource implements CandidateSourceInterface
{
    public function sourceId(): string
    {
        return 'popular';
    }

    public function generate(FeedContext $context): CandidateCollection
    {
        $limit = (int) config('recommendation.cache.snapshot_size', 500);

        /** @var list<string> $videoIds */
        $videoIds = Video::query()
            ->where('status', VideoStatus::Published)
            ->where('visibility', VideoVisibility::Public)
            ->orderByDesc('like_count')
            ->orderByDesc('view_count')
            ->orderByDesc('comment_count')
            ->orderByDesc('share_count')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $items = array_map(
            static fn (string $videoId): Candidate => new Candidate($videoId, ['popular']),
            $videoIds,
        );

        return new CandidateCollection($items);
    }
}
