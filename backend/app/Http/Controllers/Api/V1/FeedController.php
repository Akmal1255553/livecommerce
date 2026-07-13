<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Recommendation\RecommendationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentItemResource;
use App\Http\Resources\VideoResource;
use App\Http\Responses\ApiResponse;
use App\Services\Recommendation\DTOs\ContentItem;
use App\Services\Video\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function __construct(
        private readonly RecommendationServiceInterface $recommendations,
        private readonly VideoService $videoService,
    ) {}

    public function trending(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->recommendations->feedTrending($request->query('cursor'), $limit, $request->user());

        return ApiResponse::cursorPaginated(
            VideoResource::collection($page->items),
            $page,
        );
    }

    public function popular(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->recommendations->feedPopular($request->query('cursor'), $limit, $request->user());

        return ApiResponse::cursorPaginated(
            VideoResource::collection($page->items),
            $page,
        );
    }

    public function newFeed(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->recommendations->feedNew($request->query('cursor'), $limit, $request->user());

        return ApiResponse::cursorPaginated(
            VideoResource::collection($page->items),
            $page,
        );
    }

    public function forYou(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->recommendations->feedForYou($request->query('cursor'), $limit, $request->user());

        return ApiResponse::cursorPaginated(
            $this->contentItemCollection($page->items),
            $page,
        );
    }

    public function following(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->videoService->feedFollowing($request->user(), $request->query('cursor'), $limit);

        return ApiResponse::cursorPaginated(
            VideoResource::collection($page->items),
            $page,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ContentItem>  $items
     */
    private function contentItemCollection($items): AnonymousResourceCollection
    {
        $resources = $items->map(
            static fn (ContentItem $item): ContentItemResource => new ContentItemResource(
                $item->type,
                $item->payload,
            ),
        );

        return ContentItemResource::collection($resources);
    }
}
