<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtService
{
    public function issueAccessToken(User $user): string
    {
        $ttl = (int) config('jwt.ttl', 15);
        $now = time();

        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($ttl * 60),
            'type' => 'access',
            'role' => $user->role->value,
        ];

        return JWT::encode($payload, $this->secret(), config('jwt.algo', 'HS256'));
    }

    public function getExpiresInSeconds(): int
    {
        return (int) config('jwt.ttl', 15) * 60;
    }

    public function getRefreshTtlMinutes(): int
    {
        return (int) config('jwt.refresh_ttl', 43200);
    }

    public function authenticate(?string $token): ?User
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secret(), config('jwt.algo', 'HS256')));
            $payload = (array) $decoded;

            if (($payload['type'] ?? null) !== 'access') {
                return null;
            }

            $user = User::query()->find($payload['sub'] ?? null);

            if ($user === null || ! $user->isActive()) {
                return null;
            }

            return $user;
        } catch (Throwable) {
            return null;
        }
    }

    public function generateRefreshToken(): string
    {
        return Str::random(64);
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function secret(): string
    {
        $secret = config('jwt.secret');

        if (! is_string($secret) || $secret === '') {
            throw new UnauthorizedException('JWT secret is not configured.');
        }

        return $secret;
    }
}
