<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

final readonly class CartViewData
{
    /**
     * @param  list<CartLinePricing>  $lines
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $version,
        public array $lines,
        public CartPricingResult $pricing,
    ) {}
}
