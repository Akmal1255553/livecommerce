<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\StoreMetricsEventsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(private readonly MetricsService $metricsService) {}

    public function store(StoreMetricsEventsRequest $request): JsonResponse
    {
        $accepted = $this->metricsService->recordEvents(
            $request->user(),
            $request->validated('events'),
        );

        return ApiResponse::accepted(['accepted' => $accepted]);
    }
}
