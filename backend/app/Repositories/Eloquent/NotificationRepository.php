<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;
use Illuminate\Support\Collection;

class NotificationRepository extends BaseEloquentRepository implements NotificationRepositoryInterface
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Notification
    {
        /** @var Notification */
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * @return Collection<int, Notification>
     */
    public function cursorPaginateForUser(string $userId, ?int $beforeId, int $limit): Collection
    {
        $query = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit + 1)->get();
    }

    public function countUnread(string $userId): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(int $id, string $userId): ?Notification
    {
        /** @var Notification|null $notification */
        $notification = $this->model->newQuery()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($notification === null || $notification->read_at !== null) {
            return $notification;
        }

        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }

    public function markAllRead(string $userId): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
