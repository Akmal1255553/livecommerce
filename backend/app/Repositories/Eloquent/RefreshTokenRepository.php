<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;
use DateTimeInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly RefreshToken $model) {}

    public function create(string $userId, string $tokenHash, ?string $deviceId, DateTimeInterface $expiresAt): RefreshToken
    {
        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'device_id' => $deviceId,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);
    }

    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return $this->model->newQuery()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revoke(RefreshToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
