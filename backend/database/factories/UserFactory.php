<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'status' => UserStatus::Active,
            'locale' => 'uz',
            'email_verified_at' => now(),
            'phone_verified_at' => null,
            'is_verified' => false,
        ];
    }

    public function withPhone(string $phone = '+998901234567'): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);
    }

    public function unverifiedPhone(string $phone = '+998901234567'): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $phone,
            'phone_verified_at' => null,
        ]);
    }

    public function seller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Seller,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => $user->username, 'notification_settings' => []],
            );
        });
    }
}
