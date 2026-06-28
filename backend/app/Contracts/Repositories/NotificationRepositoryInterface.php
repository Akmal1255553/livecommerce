<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Notification;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Notification;

    /**
     * @return Collection<int, Notification>
     */
    public function cursorPaginateForUser(string $userId, ?int $beforeId, int $limit): Collection;

    public function countUnread(string $userId): int;

    public function markRead(int $id, string $userId): ?Notification;

    public function markAllRead(string $userId): int;
}
