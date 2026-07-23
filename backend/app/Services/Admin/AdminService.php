<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Enums\ContentReportStatus;
use App\Enums\ContentReportTarget;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Enums\VideoStatus;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Logging\StructuredLogger;
use App\Models\Category;
use App\Models\ContentReport;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Services\Audit\AuditService;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AdminService extends BaseService
{
    public function __construct(
        StructuredLogger $logger,
        private readonly AuditService $audit,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function listUsers(?string $status, ?string $role, int $page, int $perPage): LengthAwarePaginator
    {
        $query = User::query()->with('profile')->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', UserStatus::from($status));
        }

        if ($role !== null) {
            $query->where('role', UserRole::from($role));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function suspendUser(User $admin, string $userId, Request $request): User
    {
        $user = $this->findUserOrFail($userId);
        $this->guardSelf($admin, $user);

        $old = ['status' => $user->status->value];
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->audit->record($admin, 'user.suspend', 'user', $userId, $old, ['status' => UserStatus::Suspended->value], $request);

        return $user->fresh() ?? $user;
    }

    public function banUser(User $admin, string $userId, Request $request): User
    {
        $user = $this->findUserOrFail($userId);
        $this->guardSelf($admin, $user);

        $old = ['status' => $user->status->value];
        $user->forceFill(['status' => UserStatus::Banned])->save();
        $this->refreshTokens->revokeAllForUser($userId);

        $this->audit->record($admin, 'user.ban', 'user', $userId, $old, ['status' => UserStatus::Banned->value], $request);

        return $user->fresh() ?? $user;
    }

    public function activateUser(User $admin, string $userId, Request $request): User
    {
        $user = $this->findUserOrFail($userId);

        $old = ['status' => $user->status->value];
        $user->forceFill(['status' => UserStatus::Active])->save();

        $this->audit->record($admin, 'user.activate', 'user', $userId, $old, ['status' => UserStatus::Active->value], $request);

        return $user->fresh() ?? $user;
    }

    /**
     * @return LengthAwarePaginator<int, Store>
     */
    public function listPendingStores(int $page, int $perPage = 20): LengthAwarePaginator
    {
        return Store::query()
            ->with('user')
            ->where('status', StoreStatus::Pending)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function approveStore(User $admin, string $storeId, Request $request): Store
    {
        $store = $this->findStoreOrFail($storeId);

        $old = ['status' => $store->status->value];
        $store->forceFill(['status' => StoreStatus::Active])->save();

        $this->audit->record($admin, 'store.approve', 'store', $storeId, $old, ['status' => StoreStatus::Active->value], $request);

        return $store->fresh() ?? $store;
    }

    public function rejectStore(User $admin, string $storeId, Request $request): Store
    {
        $store = $this->findStoreOrFail($storeId);

        $old = ['status' => $store->status->value];
        $store->forceFill(['status' => StoreStatus::Suspended])->save();

        // Revert user role to User if they have no other active store
        $user = $store->user;
        if ($user !== null && $user->hasRole(UserRole::Seller)) {
            $activeStores = Store::query()
                ->where('user_id', $user->id)
                ->where('status', StoreStatus::Active)
                ->where('id', '!=', $storeId)
                ->exists();

            if (! $activeStores) {
                $user->forceFill(['role' => UserRole::User])->save();
            }
        }

        $this->audit->record($admin, 'store.reject', 'store', $storeId, $old, ['status' => StoreStatus::Suspended->value], $request);

        return $store->fresh() ?? $store;
    }

    /**
     * @return LengthAwarePaginator<int, Video>
     */
    public function listPendingVideos(int $page, int $perPage = 20): LengthAwarePaginator
    {
        return Video::query()
            ->with('user')
            ->where('status', VideoStatus::Queued)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function approveVideo(User $admin, string $videoId, Request $request): Video
    {
        $video = $this->findVideoOrFail($videoId);

        $old = ['status' => $video->status->value];
        $video->forceFill(['status' => VideoStatus::Published, 'published_at' => now()])->save();

        $this->audit->record($admin, 'video.approve', 'video', $videoId, $old, ['status' => VideoStatus::Published->value], $request);

        return $video->fresh() ?? $video;
    }

    public function rejectVideo(User $admin, string $videoId, Request $request): Video
    {
        $video = $this->findVideoOrFail($videoId);

        $old = ['status' => $video->status->value];
        $video->forceFill(['status' => VideoStatus::Rejected])->save();

        $this->audit->record($admin, 'video.reject', 'video', $videoId, $old, ['status' => VideoStatus::Rejected->value], $request);

        return $video->fresh() ?? $video;
    }

    public function hideVideo(User $admin, string $videoId, Request $request): Video
    {
        $video = $this->findVideoOrFail($videoId);

        $old = ['status' => $video->status->value];
        $video->forceFill(['status' => VideoStatus::Hidden])->save();

        $this->audit->record($admin, 'video.hide', 'video', $videoId, $old, ['status' => VideoStatus::Hidden->value], $request);

        return $video->fresh() ?? $video;
    }

    /**
     * @return LengthAwarePaginator<int, \App\Models\AuditLog>
     */
    public function listAuditLogs(int $page, int $perPage = 20): LengthAwarePaginator
    {
        return \App\Models\AuditLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Platform snapshot for the admin dashboard (integer minor units only).
     *
     * @return array{
     *     users_total: int,
     *     users_active: int,
     *     users_suspended: int,
     *     users_banned: int,
     *     stores_pending: int,
     *     stores_active: int,
     *     videos_pending: int,
     *     reports_open: int,
     *     orders_total: int,
     *     orders_paid_or_later: int,
     *     revenue_paid_minor: int,
     *     currency: string
     * }
     */
    public function overview(): array
    {
        $paidOrLater = [
            OrderStatus::Paid,
            OrderStatus::Packing,
            OrderStatus::ReadyToShip,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed,
            OrderStatus::RefundRequested,
            OrderStatus::RefundApproved,
            OrderStatus::RefundRejected,
            OrderStatus::Refunded,
        ];

        return [
            'users_total' => User::query()->count(),
            'users_active' => User::query()->where('status', UserStatus::Active)->count(),
            'users_suspended' => User::query()->where('status', UserStatus::Suspended)->count(),
            'users_banned' => User::query()->where('status', UserStatus::Banned)->count(),
            'stores_pending' => Store::query()->where('status', StoreStatus::Pending)->count(),
            'stores_active' => Store::query()->where('status', StoreStatus::Active)->count(),
            'videos_pending' => Video::query()->where('status', VideoStatus::Queued)->count(),
            'reports_open' => ContentReport::query()->where('status', ContentReportStatus::Pending)->count(),
            'orders_total' => Order::query()->count(),
            'orders_paid_or_later' => Order::query()->whereIn('status', $paidOrLater)->count(),
            'revenue_paid_minor' => (int) Order::query()
                ->where('payment_status', PaymentStatus::Paid)
                ->sum('total'),
            'currency' => 'UZS',
        ];
    }

    /**
     * Flat category list for admin (includes inactive).
     *
     * @return EloquentCollection<int, Category>
     */
    public function listCategories(): EloquentCollection
    {
        return Category::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function createReport(User $reporter, string $targetType, string $targetId, string $reason): ContentReport
    {
        /** @var ContentReport $report */
        $report = ContentReport::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => ContentReportTarget::from($targetType),
            'target_id' => $targetId,
            'reason' => $reason,
            'status' => ContentReportStatus::Pending,
        ]);

        return $report;
    }

    /**
     * @return LengthAwarePaginator<int, ContentReport>
     */
    public function listReports(?string $status, int $page, int $perPage = 20): LengthAwarePaginator
    {
        $query = ContentReport::query()
            ->with(['reporter', 'resolver'])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', ContentReportStatus::from($status));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function resolveReport(User $admin, string $reportId, ?string $note, Request $request): ContentReport
    {
        $report = $this->findReportOrFail($reportId);

        $report->forceFill([
            'status' => ContentReportStatus::Resolved,
            'resolved_by' => $admin->id,
            'resolution_note' => $note,
        ])->save();

        $this->audit->record($admin, 'report.resolve', 'content_report', $reportId, null, ['status' => ContentReportStatus::Resolved->value], $request);

        return $report->fresh() ?? $report;
    }

    public function dismissReport(User $admin, string $reportId, ?string $note, Request $request): ContentReport
    {
        $report = $this->findReportOrFail($reportId);

        $report->forceFill([
            'status' => ContentReportStatus::Dismissed,
            'resolved_by' => $admin->id,
            'resolution_note' => $note,
        ])->save();

        $this->audit->record($admin, 'report.dismiss', 'content_report', $reportId, null, ['status' => ContentReportStatus::Dismissed->value], $request);

        return $report->fresh() ?? $report;
    }

    private function findUserOrFail(string $userId): User
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw new ResourceNotFoundException('User not found.');
        }

        return $user;
    }

    private function findStoreOrFail(string $storeId): Store
    {
        $store = Store::query()->find($storeId);
        if ($store === null) {
            throw new ResourceNotFoundException('Store not found.');
        }

        return $store;
    }

    private function findVideoOrFail(string $videoId): Video
    {
        $video = Video::query()->find($videoId);
        if ($video === null) {
            throw new ResourceNotFoundException('Video not found.');
        }

        return $video;
    }

    private function findReportOrFail(string $reportId): ContentReport
    {
        $report = ContentReport::query()->find($reportId);
        if ($report === null) {
            throw new ResourceNotFoundException('Report not found.');
        }

        return $report;
    }

    private function guardSelf(User $admin, User $target): void
    {
        if ($admin->id === $target->id) {
            throw new ForbiddenException('You cannot perform this action on yourself.');
        }
    }
}
