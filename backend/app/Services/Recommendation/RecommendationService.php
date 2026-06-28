<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Contracts\Recommendation\RecommendationServiceInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\FeedStrategy;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use App\Services\Recommendation\DTOs\FeedContext;
use App\Services\Video\VideoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RecommendationService extends BaseService implements RecommendationServiceInterface
{
    public function __construct(
        private readonly RecommendationEngineInterface $engine,
        private readonly CandidateGeneratorService $candidateGenerator,
        private readonly VideoRepositoryInterface $videos,
        private readonly VideoService $videoService,
    ) {}

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedTrending(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildRankedFeed(FeedStrategy::Trending, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedPopular(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildRankedFeed(FeedStrategy::Popular, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedNew(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        $items = $this->videos->listNewCandidates(
            $limit,
            CursorPaginationData::decodeVideoCursor($cursor),
        );

        $page = $this->buildVideoCursorPage($items, $limit);
        $enriched = $this->videoService->enrichVideosForViewer($page->items, $viewer);

        return CursorPaginationData::metaWithStrategy(
            items: $enriched,
            nextCursor: $page->nextCursor,
            hasMore: $page->hasMore,
            limit: $page->limit,
            strategy: FeedStrategy::New->value,
            snapshot: null,
            engine: null,
        );
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedForYou(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildRankedFeed(FeedStrategy::ForYou, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<Video>
     */
    private function buildRankedFeed(
        FeedStrategy $strategy,
        ?string $cursor,
        int $limit,
        ?User $viewer,
    ): CursorPaginationData {
        $snapshot = $this->getOrBuildSnapshot($strategy, $viewer);
        $decoded = CursorPaginationData::decodeRankedCursor($cursor) ?? [
            'snapshot' => $snapshot['version'],
            'offset' => 0,
        ];

        if ($decoded['snapshot'] !== $snapshot['version']) {
            $decoded['offset'] = 0;
        }

        $offset = $decoded['offset'];
        $ids = array_slice($snapshot['ids'], $offset, $limit + 1);
        $hasMore = count($ids) > $limit;

        if ($hasMore) {
            $ids = array_slice($ids, 0, $limit);
        }

        $videos = $this->videos->findPublishedByIds($ids);
        $enriched = $this->videoService->enrichVideosForViewer($videos, $viewer);

        return CursorPaginationData::metaWithStrategy(
            items: $enriched,
            nextCursor: $hasMore
                ? CursorPaginationData::encodeRankedCursor($snapshot['version'], $offset + $limit)
                : null,
            hasMore: $hasMore,
            limit: $limit,
            strategy: $strategy->value,
            snapshot: $snapshot['version'],
            engine: (string) config('recommendation.engine', 'rule'),
        );
    }

    /**
     * @return array{version: string, ids: list<string>}
     */
    private function getOrBuildSnapshot(FeedStrategy $strategy, ?User $viewer): array
    {
        $cacheKey = $this->snapshotCacheKey($strategy, $viewer);

        if ($cacheKey !== null) {
            /** @var array{version: string, ids: list<string>}|null $cached */
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $context = new FeedContext(
            strategy: $strategy,
            user: $viewer,
            limit: (int) config('recommendation.cache.snapshot_size', 500),
        );

        $candidates = $this->candidateGenerator->generate($context);
        $ranked = $this->engine->rank($context, $candidates);
        $ids = $ranked->videoIds();
        $version = $ranked->snapshot ?? sha1(implode(',', $ids));

        $snapshot = ['version' => $version, 'ids' => $ids];

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $snapshot, $this->snapshotTtl($strategy));
        }

        return $snapshot;
    }

    private function snapshotCacheKey(FeedStrategy $strategy, ?User $viewer): ?string
    {
        return match ($strategy) {
            FeedStrategy::Trending => 'feed:trending:snapshot',
            FeedStrategy::Popular => 'feed:popular:snapshot',
            FeedStrategy::ForYou => $viewer !== null ? "feed:for_you:{$viewer->id}:snapshot" : null,
            default => null,
        };
    }

    private function snapshotTtl(FeedStrategy $strategy): int
    {
        return match ($strategy) {
            FeedStrategy::Trending => (int) config('recommendation.cache.trending_ttl', 300),
            FeedStrategy::Popular => (int) config('recommendation.cache.popular_ttl', 900),
            FeedStrategy::ForYou => (int) config('recommendation.cache.for_you_ttl', 300),
            default => 300,
        };
    }

    /**
     * @param  Collection<int, Video>  $items
     * @return CursorPaginationData<Video>
     */
    private function buildVideoCursorPage(Collection $items, int $limit): CursorPaginationData
    {
        $hasMore = $items->count() > $limit;

        if ($hasMore) {
            $items = $items->take($limit);
        }

        return new CursorPaginationData(
            items: $items,
            nextCursor: CursorPaginationData::nextVideoCursor($items, $hasMore),
            hasMore: $hasMore,
            limit: $limit,
        );
    }
}
