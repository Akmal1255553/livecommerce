<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\HealthServiceInterface;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly HealthServiceInterface $healthService,
    ) {}

    /**
     * Health check
     *
     * Returns platform connectivity status for database, Redis, and queue.
     */
    public function __invoke(): JsonResponse
    {
        $health = $this->healthService->check();

        return ApiResponse::make(
            success: $health->isHealthy(),
            data: $health->toArray(),
            status: $health->httpStatusCode(),
        );
    }
}
