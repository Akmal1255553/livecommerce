<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;

class ProfileService extends BaseService
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    public function getProfile(User $user): User
    {
        return $user->loadMissing('profile');
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

        return $user->fresh(['profile']);
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
        $this->refreshTokens->revokeAllForUser($user->id);
        $user->delete();
    }
}
