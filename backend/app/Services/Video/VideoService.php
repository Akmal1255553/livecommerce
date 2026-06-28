<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\BookmarkRepositoryInterface;
use App\Contracts\Repositories\VideoLikeRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VideoService extends BaseService
{
    public function __construct(
        private readonly VideoRepositoryInterface $videos,
        private readonly VideoLikeRepositoryInterface $likes,
        private readonly BookmarkRepositoryInterface $bookmarks,
    ) {}

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedForYou(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData
    {
        $page = $this->buildVideoCursorPage(
            $this->videos->cursorPaginateFeed(
                CursorPaginationData::decodeVideoCursor($cursor),
                $limit,
            ),
            $limit,
        );

        $page = new CursorPaginationData(
            items: $this->enrichVideosForViewer($page->items, $viewer),
            nextCursor: $page->nextCursor,
            hasMore: $page->hasMore,
            limit: $page->limit,
        );

        return $page;
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedFollowing(User $viewer, ?string $cursor, int $limit): CursorPaginationData
    {
        $followingIds = DB::table('follows')
            ->where('follower_id', $viewer->id)
            ->pluck('following_id')
            ->all();

        if ($followingIds === []) {
            return new CursorPaginationData(collect(), null, false, $limit);
        }

        $page = $this->buildVideoCursorPage(
            $this->videos->cursorPaginateFeed(
                CursorPaginationData::decodeVideoCursor($cursor),
                $limit,
                $followingIds,
            ),
            $limit,
        );

        return new CursorPaginationData(
            items: $this->enrichVideosForViewer($page->items, $viewer),
            nextCursor: $page->nextCursor,
            hasMore: $page->hasMore,
            limit: $page->limit,
        );
    }

    /**
     * @param  Collection<int, Video>  $videos
     * @return Collection<int, Video>
     */
    public function enrichVideosForViewer(Collection $videos, ?User $viewer): Collection
    {
        if ($viewer === null || $videos->isEmpty()) {
            return $videos;
        }

        $videoIds = $videos->pluck('id')->all();
        $likedIds = array_flip($this->likes->likedVideoIdsForUser($viewer->id, $videoIds));
        $bookmarkedIds = array_flip($this->bookmarks->bookmarkedVideoIdsForUser($viewer->id, $videoIds));

        return $videos->map(function (Video $video) use ($likedIds, $bookmarkedIds): Video {
            $video->setAttribute('is_liked', isset($likedIds[$video->id]));
            $video->setAttribute('is_bookmarked', isset($bookmarkedIds[$video->id]));

            return $video;
        });
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
