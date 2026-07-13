<?php

declare(strict_types=1);

namespace App\Contracts\Recommendation;

use App\DTOs\Pagination\CursorPaginationData;
use App\Models\User;
use App\Models\Video;
use App\Services\Recommendation\DTOs\ContentItem;

interface RecommendationServiceInterface
{
    /**
     * @return CursorPaginationData<Video>
     */
    public function feedTrending(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData;

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedPopular(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData;

    /**
     * @return CursorPaginationData<Video>
     */
    public function feedNew(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData;

    /**
     * @return CursorPaginationData<ContentItem>
     */
    public function feedForYou(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData;

    /**
     * @return CursorPaginationData<ContentItem>
     */
    public function feedDiscover(?string $cursor, int $limit, ?User $viewer = null): CursorPaginationData;
}
