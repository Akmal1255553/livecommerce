<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Recommendation\RecommendationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\Recommendation\DTOs\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoverController extends Controller
{
    public function __construct(
        private readonly RecommendationServiceInterface $recommendations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->recommendations->feedDiscover($request->query('cursor'), $limit, $request->user());

        $resources = $page->items->map(
            static fn (ContentItem $item): ContentItemResource => new ContentItemResource(
                $item->type,
                $item->payload,
            ),
        );

        return ApiResponse::cursorPaginated(
            ContentItemResource::collection($resources),
            $page,
        );
    }
}
