<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVideoController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->admin->listPendingVideos($page);

        return ApiResponse::paginated(
            VideoResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $video = $this->admin->approveVideo($request->user(), $id, $request);

        return ApiResponse::success(new VideoResource($video));
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $video = $this->admin->rejectVideo($request->user(), $id, $request);

        return ApiResponse::success(new VideoResource($video));
    }

    public function hide(Request $request, string $id): JsonResponse
    {
        $video = $this->admin->hideVideo($request->user(), $id, $request);

        return ApiResponse::success(new VideoResource($video));
    }
}
