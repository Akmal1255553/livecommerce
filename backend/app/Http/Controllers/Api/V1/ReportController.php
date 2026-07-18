<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentReportResource;
use App\Http\Responses\ApiResponse;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'string', 'in:user,video,product,store'],
            'target_id' => ['required', 'string', 'max:36'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $report = $this->admin->createReport(
            $request->user(),
            $data['target_type'],
            $data['target_id'],
            $data['reason'],
        );

        return ApiResponse::created(new ContentReportResource($report));
    }
}
