<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pagination\PaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationSettingsRequest;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Follow\FollowService;
use App\Services\Notification\NotificationService;
use App\Services\Block\BlockService;
use App\Services\User\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly FollowService $followService,
        private readonly NotificationService $notificationService,
        private readonly BlockService $blockService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->profileService->getProfile($request->user());

        return ApiResponse::success(new UserResource($user));
    }

    public function showPublic(Request $request, string $id): JsonResponse
    {
        $user = $this->followService->getPublicProfile($id, $request->user());

        return ApiResponse::success(new UserResource($user));
    }

    public function follow(Request $request, string $id): JsonResponse
    {
        $target = User::query()->findOrFail($id);
        $this->authorize('follow', $target);

        $this->followService->follow($request->user(), $id);

        return ApiResponse::created(['message' => 'User followed successfully.']);
    }

    public function unfollow(Request $request, string $id): JsonResponse
    {
        $this->followService->unfollow($request->user(), $id);

        return ApiResponse::success(['message' => 'User unfollowed successfully.']);
    }

    public function block(Request $request, string $id): JsonResponse
    {
        $this->blockService->block($request->user(), $id);

        return ApiResponse::created(['message' => 'User blocked.']);
    }

    public function unblock(Request $request, string $id): JsonResponse
    {
        $this->blockService->unblock($request->user(), $id);

        return ApiResponse::success(['message' => 'User unblocked.']);
    }

    public function followers(Request $request, string $id): JsonResponse
    {
        [$page, $perPage] = $this->paginationParams($request);
        $paginator = $this->followService->paginateFollowers($id, $page, $perPage);

        return ApiResponse::paginated(
            UserResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function following(Request $request, string $id): JsonResponse
    {
        [$page, $perPage] = $this->paginationParams($request);
        $paginator = $this->followService->paginateFollowing($id, $page, $perPage);

        return ApiResponse::paginated(
            UserResource::collection($paginator->items()),
            PaginationData::fromPaginator($paginator),
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(new UserResource($user));
    }

    public function updateNotificationSettings(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $user = $this->notificationService->updateSettings(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(new UserResource($user));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->profileService->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success(['message' => 'Password updated successfully.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->profileService->deleteAccount($request->user());

        return ApiResponse::success(['message' => 'Account deleted successfully.']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function paginationParams(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 20)), 50);

        return [$page, $perPage];
    }
}
