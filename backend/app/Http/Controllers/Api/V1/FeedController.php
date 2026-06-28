<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Http\Responses\ApiResponse;
use App\Services\Video\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private readonly VideoService $videoService) {}

    public function forYou(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->videoService->feedForYou($request->query('cursor'), $limit);

        return ApiResponse::cursorPaginated(
            VideoResource::collection($page->items),
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
}
