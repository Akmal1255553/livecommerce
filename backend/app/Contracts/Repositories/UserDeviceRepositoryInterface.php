<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\UserDevice;

interface UserDeviceRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertDevice(array $attributes): UserDevice;

    public function deactivate(string $userId, string $fcmToken): bool;

    /**
     * @return list<string>
     */
    public function activeTokensForUser(string $userId): array;
}
