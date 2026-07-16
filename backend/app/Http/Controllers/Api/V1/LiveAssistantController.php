<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\LiveAssistantServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveAssistantController extends Controller
{
    public function __construct(
        private readonly LiveAssistantServiceInterface $assistant,
    ) {}

    public function suggestions(Request $request, string $id): JsonResponse
    {
        $suggestions = $this->assistant->suggestionsForHost($request->user(), $id);

        return ApiResponse::success([
            'provider' => 'rule_based',
            'suggestions' => $suggestions->map(fn ($s) => $s->toArray())->values()->all(),
        ]);
    }
}
