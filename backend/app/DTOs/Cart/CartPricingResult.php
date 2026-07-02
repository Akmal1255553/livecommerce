<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

use App\ValueObjects\Money;

final readonly class CartPricingResult
{
    /**
     * @param  list<CartLinePricing>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $discountTotal,
        public Money $shippingEstimate,
        public int $itemCount,
    ) {}
}
