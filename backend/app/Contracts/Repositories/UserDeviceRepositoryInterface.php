<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface UserDeviceRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertDevice(array $attributes): \App\Models\UserDevice;

    public function deactivate(string $userId, string $fcmToken): bool;

    /**
     * @return list<string>
     */
    public function activeTokensForUser(string $userId): array;
}
