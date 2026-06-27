<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\DTOs\DataTransferObject;
use App\Models\User;

final readonly class AuthResultData extends DataTransferObject
{
    public function __construct(
        public readonly User $user,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->expiresIn,
        ];
    }
}
