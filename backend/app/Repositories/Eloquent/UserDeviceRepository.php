<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\Models\UserDevice;

class UserDeviceRepository extends BaseEloquentRepository implements UserDeviceRepositoryInterface
{
    public function __construct(UserDevice $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertDevice(array $attributes): UserDevice
    {
        /** @var UserDevice */
        return $this->model->newQuery()->updateOrCreate(
            [
                'user_id' => $attributes['user_id'],
                'fcm_token' => $attributes['fcm_token'],
            ],
            [
                'platform' => $attributes['platform'],
                'device_id' => $attributes['device_id'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );
    }

    public function deactivate(string $userId, string $fcmToken): bool
    {
        return (bool) $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('fcm_token', $fcmToken)
            ->update(['is_active' => false]);
    }

    /**
     * @return list<string>
     */
    public function activeTokensForUser(string $userId): array
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->all();
    }
}
