<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Video\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Video\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoCommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        $paginator = $this->comments->listForVideo($id, $page, $perPage);

        return ApiResponse::paginated(
            CommentResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function store(StoreCommentRequest $request, string $id): JsonResponse
    {
        $comment = $this->comments->create(
            $request->user(),
            $id,
            $request->validated('body'),
            $request->validated('session_id'),
            $request->validated('parent_id'),
        );

        return ApiResponse::created(new CommentResource($comment));
    }

    public function destroy(Request $request, string $id, int $commentId): JsonResponse
    {
        $this->comments->deleteOwn($request->user(), $id, $commentId);

        return ApiResponse::noContent();
    }
}
