<?php

declare(strict_types=1);

namespace App\Services\Video;

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
    ) {}

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedForYou(?string $cursor, int $limit): CursorPaginationData
    {
        return $this->buildVideoCursorPage(
            $this->videos->cursorPaginateFeed(
                CursorPaginationData::decodeVideoCursor($cursor),
                $limit,
            ),
            $limit,
        );
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

        return $this->buildVideoCursorPage(
            $this->videos->cursorPaginateFeed(
                CursorPaginationData::decodeVideoCursor($cursor),
                $limit,
                $followingIds,
            ),
            $limit,
        );
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
