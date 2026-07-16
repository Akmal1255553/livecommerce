<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\LiveAnalyticsServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveAnalyticsController extends Controller
{
    public function __construct(
        private readonly LiveAnalyticsServiceInterface $analytics,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $overview = $this->analytics->overviewForSeller($request->user(), $limit);

        return ApiResponse::success($overview->toArray());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->analytics->assertSellerOwnsSession($request->user(), $id);
        $summary = $this->analytics->summarizeSession($id);

        return ApiResponse::success($summary->toArray());
    }
}
