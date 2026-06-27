<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\UserProfile;

class CreateUserProfile
{
    public function handle(UserRegistered $event): void
    {
        UserProfile::query()->firstOrCreate(
            ['user_id' => $event->user->id],
            [
                'display_name' => $event->user->username,
                'notification_settings' => [],
            ],
        );
    }
}
