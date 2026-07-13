<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Contracts\Recommendation\RecommendationEngineInterface;
use App\Contracts\Recommendation\RecommendationServiceInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\ContentType;
use App\Enums\FeedStrategy;
use App\Models\LiveSession;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use App\Services\Recommendation\DTOs\ContentItem;
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
        return $this->buildVideoOnlyRankedFeed(FeedStrategy::Trending, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedPopular(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildVideoOnlyRankedFeed(FeedStrategy::Popular, $cursor, $limit, $viewer);
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
     * @return CursorPaginationData<ContentItem>
     */
    public function feedForYou(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildMixedRankedFeed(FeedStrategy::ForYou, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<ContentItem>
     */
    public function feedDiscover(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        return $this->buildMixedRankedFeed(FeedStrategy::Discover, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<Video>
     */
    private function buildVideoOnlyRankedFeed(
        FeedStrategy $strategy,
        ?string $cursor,
        int $limit,
        ?User $viewer,
    ): CursorPaginationData {
        $snapshot = $this->getOrBuildSnapshot($strategy, $viewer);
        $keys = array_values(array_filter(
            $snapshot['keys'],
            static fn (string $key): bool => str_starts_with($key, 'video:'),
        ));

        return $this->paginateVideoKeys($keys, $snapshot['version'], $strategy, $cursor, $limit, $viewer);
    }

    /**
     * @return CursorPaginationData<ContentItem>
     */
    private function buildMixedRankedFeed(
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
        $keys = array_slice($snapshot['keys'], $offset, $limit + 1);
        $hasMore = count($keys) > $limit;

        if ($hasMore) {
            $keys = array_slice($keys, 0, $limit);
        }

        $items = $this->hydrateContentItems($keys, $viewer);

        return CursorPaginationData::metaWithStrategy(
            items: $items,
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
     * @param  list<string>  $keys
     * @return CursorPaginationData<Video>
     */
    private function paginateVideoKeys(
        array $keys,
        string $version,
        FeedStrategy $strategy,
        ?string $cursor,
        int $limit,
        ?User $viewer,
    ): CursorPaginationData {
        $decoded = CursorPaginationData::decodeRankedCursor($cursor) ?? [
            'snapshot' => $version,
            'offset' => 0,
        ];

        if ($decoded['snapshot'] !== $version) {
            $decoded['offset'] = 0;
        }

        $offset = $decoded['offset'];
        $pageKeys = array_slice($keys, $offset, $limit + 1);
        $hasMore = count($pageKeys) > $limit;

        if ($hasMore) {
            $pageKeys = array_slice($pageKeys, 0, $limit);
        }

        $ids = array_map(
            static fn (string $key): string => substr($key, strlen('video:')),
            $pageKeys,
        );

        $videos = $this->videos->findPublishedByIds($ids);
        $enriched = $this->videoService->enrichVideosForViewer($videos, $viewer);

        return CursorPaginationData::metaWithStrategy(
            items: $enriched,
            nextCursor: $hasMore
                ? CursorPaginationData::encodeRankedCursor($version, $offset + $limit)
                : null,
            hasMore: $hasMore,
            limit: $limit,
            strategy: $strategy->value,
            snapshot: $version,
            engine: (string) config('recommendation.engine', 'rule'),
        );
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<int, ContentItem>
     */
    private function hydrateContentItems(array $keys, ?User $viewer): Collection
    {
        $videoIds = [];
        $liveIds = [];

        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
            if ($type === ContentType::Video->value && $id !== null) {
                $videoIds[] = $id;
            }
            if ($type === ContentType::Live->value && $id !== null) {
                $liveIds[] = $id;
            }
        }

        $videos = $this->videos->findPublishedByIds($videoIds)->keyBy('id');
        $enrichedVideos = $this->videoService->enrichVideosForViewer($videos->values(), $viewer)->keyBy('id');

        $lives = collect();
        if ($liveIds !== []) {
            $lives = LiveSession::query()
                ->with(['seller.profile', 'store', 'viewerMetrics', 'sessionProducts.product.images'])
                ->whereIn('id', $liveIds)
                ->get()
                ->keyBy('id');
        }

        $items = collect();

        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, null);

            if ($type === ContentType::Video->value && $id !== null && $enrichedVideos->has($id)) {
                $items->push(new ContentItem(ContentType::Video, $enrichedVideos->get($id)));
            }

            if ($type === ContentType::Live->value && $id !== null && $lives->has($id)) {
                $items->push(new ContentItem(ContentType::Live, $lives->get($id)));
            }
        }

        return $items;
    }

    /**
     * @return array{version: string, keys: list<string>}
     */
    private function getOrBuildSnapshot(FeedStrategy $strategy, ?User $viewer): array
    {
        $cacheKey = $this->snapshotCacheKey($strategy, $viewer);

        if ($cacheKey !== null && $this->shouldUseSnapshotCache()) {
            /** @var array{version: string, keys: list<string>}|null $cached */
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && isset($cached['keys'])) {
                return $cached;
            }

            // Legacy snapshot shape from video-only feeds.
            if (is_array($cached) && isset($cached['ids']) && is_array($cached['ids'])) {
                return [
                    'version' => $cached['version'],
                    'keys' => array_map(
                        static fn (string $id): string => ContentType::Video->value.':'.$id,
                        $cached['ids'],
                    ),
                ];
            }
        }

        $context = new FeedContext(
            strategy: $strategy,
            user: $viewer,
            limit: (int) config('recommendation.cache.snapshot_size', 500),
        );

        $candidates = $this->candidateGenerator->generate($context);
        $ranked = $this->engine->rank($context, $candidates);
        $keys = $ranked->contentKeys();
        $version = $ranked->snapshot ?? sha1(implode(',', $keys));

        $snapshot = ['version' => $version, 'keys' => $keys];

        if ($cacheKey !== null && $this->shouldUseSnapshotCache()) {
            Cache::put($cacheKey, $snapshot, $this->snapshotTtl($strategy));
        }

        return $snapshot;
    }

    private function shouldUseSnapshotCache(): bool
    {
        return ! app()->environment('testing');
    }

    private function snapshotCacheKey(FeedStrategy $strategy, ?User $viewer): ?string
    {
        return match ($strategy) {
            FeedStrategy::Trending => 'feed:trending:snapshot',
            FeedStrategy::Popular => 'feed:popular:snapshot',
            FeedStrategy::ForYou => $viewer !== null ? "feed:for_you:{$viewer->id}:snapshot" : 'feed:for_you:guest:snapshot',
            FeedStrategy::Discover => $viewer !== null ? "feed:discover:{$viewer->id}:snapshot" : 'feed:discover:guest:snapshot',
            default => null,
        };
    }

    private function snapshotTtl(FeedStrategy $strategy): int
    {
        return match ($strategy) {
            FeedStrategy::Trending => (int) config('recommendation.cache.trending_ttl', 300),
            FeedStrategy::Popular => (int) config('recommendation.cache.popular_ttl', 900),
            FeedStrategy::ForYou, FeedStrategy::Discover => (int) config('recommendation.cache.for_you_ttl', 300),
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
