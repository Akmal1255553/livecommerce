<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pagination\CursorPaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Http\Responses\ApiResponse;
use App\Services\Video\VideoInteractionService;
use App\Services\Video\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function __construct(
        private readonly VideoInteractionService $interactions,
        private readonly VideoService $videoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->interactions->listBookmarks($request->user(), $request->query('cursor'), $limit);

        $videos = $this->videoService->enrichVideosForViewer($page->items, $request->user());

        return ApiResponse::cursorPaginated(
            VideoResource::collection($videos),
            new CursorPaginationData(
                items: $videos,
                nextCursor: $page->nextCursor,
                hasMore: $page->hasMore,
                limit: $page->limit,
            ),
        );
    }
}
