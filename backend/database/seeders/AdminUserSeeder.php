<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo admin for /admin web panel (composer --no-dev safe).
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@livecommerce.local';

    public const USERNAME = 'admin';

    public const PASSWORD = 'Password1!';

    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'username' => self::USERNAME,
                'password' => Hash::make(self::PASSWORD),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'locale' => 'uz',
                'email_verified_at' => now(),
            ],
        );

        if ($user->role !== UserRole::Admin || $user->status !== UserStatus::Active) {
            $user->forceFill([
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'password' => Hash::make(self::PASSWORD),
            ])->save();
        }

        UserProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Platform Admin',
                'notification_settings' => [],
            ],
        );

        $this->command?->info(
            'AdminUserSeeder: '.self::EMAIL.' / '.self::PASSWORD,
        );
    }
}
