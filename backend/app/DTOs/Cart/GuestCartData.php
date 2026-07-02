<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

final readonly class GuestCartData
{
    /**
     * @param  list<array{product_id: string, variant_id: ?int, quantity: int}>  $items
     */
    public function __construct(
        public int $version,
        public array $items,
    ) {}

    public static function empty(): self
    {
        return new self(1, []);
    }

    /**
     * @param  array{version?: int, items?: list<array{product_id: string, variant_id?: ?int, quantity: int}>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            (int) ($payload['version'] ?? 1),
            $payload['items'] ?? [],
        );
    }

    /**
     * @return array{version: int, items: list<array{product_id: string, variant_id: ?int, quantity: int}>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'items' => $this->items,
        ];
    }
}
