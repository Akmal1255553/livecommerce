<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\DTOs\Cart\GuestCartData;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class GuestCartStore
{
    private const KEY_PREFIX = 'cart:guest:';

    public function createToken(): string
    {
        return (string) Str::uuid();
    }

    public function get(string $token): ?GuestCartData
    {
        $raw = Redis::get($this->key($token));

        if ($raw === null) {
            return null;
        }

        /** @var array{version?: int, items?: list<array{product_id: string, variant_id?: ?int, quantity: int}>} $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return GuestCartData::fromArray($decoded);
    }

    public function put(string $token, GuestCartData $cart): void
    {
        $ttlSeconds = (int) config('commerce.guest_cart_ttl_days', 30) * 86400;

        Redis::setex(
            $this->key($token),
            $ttlSeconds,
            json_encode($cart->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function delete(string $token): void
    {
        Redis::del($this->key($token));
    }

    public function exists(string $token): bool
    {
        return Redis::exists($this->key($token)) > 0;
    }

    private function key(string $token): string
    {
        return self::KEY_PREFIX.$token;
    }
}
