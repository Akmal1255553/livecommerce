<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentReportResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $status = $request->query('status') !== null ? (string) $request->query('status') : null;
        $paginator = $this->admin->listReports($status, $page);

        return ApiResponse::paginated(
            ContentReportResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $note = $request->input('resolution_note')
            ?? $request->input('note');
        $note = $note !== null ? (string) $note : null;
        $report = $this->admin->resolveReport($request->user(), $id, $note, $request);

        return ApiResponse::success(new ContentReportResource($report));
    }

    public function dismiss(Request $request, string $id): JsonResponse
    {
        $note = $request->input('resolution_note') !== null ? (string) $request->input('resolution_note') : null;
        $report = $this->admin->dismissReport($request->user(), $id, $note, $request);

        return ApiResponse::success(new ContentReportResource($report));
    }
}
