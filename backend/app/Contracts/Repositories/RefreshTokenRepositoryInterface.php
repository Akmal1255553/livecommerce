<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function create(string $userId, string $tokenHash, ?string $deviceId, \DateTimeInterface $expiresAt): RefreshToken;

    public function findValidByHash(string $tokenHash): ?RefreshToken;

    public function revoke(RefreshToken $token): void;

    public function revokeAllForUser(string $userId): void;
}
