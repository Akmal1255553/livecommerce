<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->admin->listPendingStores($page);

        return ApiResponse::paginated(
            StoreResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $store = $this->admin->approveStore($request->user(), $id, $request);

        return ApiResponse::success(new StoreResource($store));
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $store = $this->admin->rejectStore($request->user(), $id, $request);

        return ApiResponse::success(new StoreResource($store));
    }
}
