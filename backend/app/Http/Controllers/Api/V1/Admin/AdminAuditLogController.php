<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->admin->listAuditLogs($page);

        return ApiResponse::paginated(
            AuditLogResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }
}
