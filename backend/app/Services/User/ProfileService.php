<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ProfileService extends BaseService
{
    public const PROFILE_CACHE_PREFIX = 'user:profile:';

    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    public static function cacheKey(string $userId): string
    {
        return self::PROFILE_CACHE_PREFIX.$userId;
    }

    public function getProfile(User $user): User
    {
        return $user->loadMissing('profile');
    }

    /**
     * Cached public profile payload (viewer-agnostic). Callers attach is_following separately.
     */
    public function getCachedUserWithProfile(string $userId): User
    {
        /** @var User $user */
        $user = Cache::remember(
            self::cacheKey($userId),
            (int) config('catalog.cache.profile_ttl', 300),
            function () use ($userId): User {
                $found = User::query()->with('profile')->find($userId);
                if (! $found instanceof User) {
                    throw new ResourceNotFoundException('User not found.');
                }

                return $found;
            },
        );

        return $user;
    }

    public function forgetProfileCache(string $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $userUpdates = array_filter([
            'bio' => $data['bio'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], fn ($value) => $value !== null);

        if ($userUpdates !== []) {
            $user->update($userUpdates);
        }

        if (isset($data['display_name'])) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['display_name' => $data['display_name']],
            );
        }

        $this->forgetProfileCache($user->id);

        return $user->fresh(['profile']) ?? $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new UnauthorizedException('Current password is incorrect.');
        }

        $user->update(['password' => $newPassword]);
        $this->refreshTokens->revokeAllForUser($user->id);
    }

    public function deleteAccount(User $user): void
    {
        $userId = $user->id;
        $this->refreshTokens->revokeAllForUser($userId);
        $user->delete();
        $this->forgetProfileCache($userId);
    }
}
