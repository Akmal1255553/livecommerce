<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

final readonly class CartContext
{
    public function __construct(
        public string $type,
        public ?string $userId = null,
        public ?string $guestToken = null,
    ) {}

    public static function forUser(string $userId): self
    {
        return new self('user', userId: $userId);
    }

    public static function forGuest(string $guestToken): self
    {
        return new self('guest', guestToken: $guestToken);
    }

    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    public function isGuest(): bool
    {
        return $this->type === 'guest';
    }
}
