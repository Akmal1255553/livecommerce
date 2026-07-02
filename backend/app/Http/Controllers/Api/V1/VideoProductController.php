<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\VideoCommerceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Video\SyncVideoProductsRequest;
use App\Http\Resources\VideoProductResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoProductController extends Controller
{
    public function __construct(
        private readonly VideoCommerceServiceInterface $videoCommerce,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $tags = $this->videoCommerce->listForVideo($id, $request->user());

        return ApiResponse::success(VideoProductResource::collection($tags));
    }

    public function sync(SyncVideoProductsRequest $request, string $id): JsonResponse
    {
        $tags = $this->videoCommerce->syncForVideo(
            $request->user(),
            $id,
            $request->validated('products'),
        );

        return ApiResponse::success(VideoProductResource::collection($tags));
    }
}
