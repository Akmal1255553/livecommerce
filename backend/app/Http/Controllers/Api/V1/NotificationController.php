<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $page = $this->notificationService->listForUser(
            $request->user(),
            $request->query('cursor'),
            $limit,
        );

        return ApiResponse::cursorPaginated(
            NotificationResource::collection($page->items),
            $page,
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()->findOrFail($id);
        $this->authorize('update', $notification);

        $updated = $this->notificationService->markRead($request->user(), $id);

        return ApiResponse::success(new NotificationResource($updated));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user());

        return ApiResponse::success([
            'message' => 'All notifications marked as read.',
            'updated_count' => $count,
        ]);
    }
}
