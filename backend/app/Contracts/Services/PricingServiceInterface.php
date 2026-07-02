<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Cart\CartPricingResult;
use App\DTOs\Cart\CouponApplication;
use App\Models\Cart;
use App\ValueObjects\Money;

interface PricingServiceInterface
{
    /**
     * @param  list<array{item_id: string|int, product_id: string, variant_id: ?int, quantity: int}>  $lines
     */
    public function priceLines(array $lines): CartPricingResult;

    public function priceCart(Cart $cart): CartPricingResult;

    public function calculateShippingSubtotal(CartPricingResult $pricing, CouponApplication $coupon): Money;
}
