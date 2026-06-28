<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Video\RecordVideoViewRequest;
use App\Http\Requests\Video\SessionScopedInteractionRequest;
use App\Http\Requests\Video\ShareVideoRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Video\VideoInteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoInteractionController extends Controller
{
    public function __construct(private readonly VideoInteractionService $interactions) {}

    public function like(SessionScopedInteractionRequest $request, string $id): JsonResponse
    {
        $result = $this->interactions->like(
            $request->user(),
            $id,
            $request->validated('session_id'),
        );
        $status = $result['created'] ? 201 : 200;

        return ApiResponse::success([
            'liked' => $result['liked'],
            'like_count' => $result['like_count'],
        ], $status);
    }

    public function unlike(Request $request, string $id): JsonResponse
    {
        $result = $this->interactions->unlike($request->user(), $id);

        return ApiResponse::success([
            'liked' => $result['liked'],
            'like_count' => $result['like_count'],
        ]);
    }

    public function view(RecordVideoViewRequest $request, string $id): JsonResponse
    {
        $this->interactions->recordView(
            $id,
            $request->validated('session_id'),
            $request->user(),
        );

        return ApiResponse::accepted();
    }

    public function share(ShareVideoRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->interactions->share(
            $request->user(),
            $id,
            \App\Enums\ShareChannel::from($validated['channel']),
            $validated['session_id'],
        );

        return ApiResponse::created([
            'share_count' => $result['share_count'],
        ]);
    }

    public function bookmark(SessionScopedInteractionRequest $request, string $id): JsonResponse
    {
        $result = $this->interactions->bookmark(
            $request->user(),
            $id,
            $request->validated('session_id'),
        );
        $status = $result['created'] ? 201 : 200;

        return ApiResponse::success([
            'bookmarked' => $result['bookmarked'],
        ], $status);
    }

    public function unbookmark(Request $request, string $id): JsonResponse
    {
        $this->interactions->unbookmark($request->user(), $id);

        return ApiResponse::success(['bookmarked' => false]);
    }
}
