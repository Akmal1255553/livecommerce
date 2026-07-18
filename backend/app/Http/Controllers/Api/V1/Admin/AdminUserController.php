<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use App\DTOs\Pagination\PaginationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);
        $status = $request->query('status') !== null ? (string) $request->query('status') : null;
        $role = $request->query('role') !== null ? (string) $request->query('role') : null;

        $paginator = $this->admin->listUsers($status, $role, $page, $perPage);

        return ApiResponse::paginated(
            UserResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        $user = $this->admin->suspendUser($request->user(), $id, $request);

        return ApiResponse::success(new UserResource($user));
    }

    public function ban(Request $request, string $id): JsonResponse
    {
        $user = $this->admin->banUser($request->user(), $id, $request);

        return ApiResponse::success(new UserResource($user));
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $user = $this->admin->activateUser($request->user(), $id, $request);

        return ApiResponse::success(new UserResource($user));
    }
}
