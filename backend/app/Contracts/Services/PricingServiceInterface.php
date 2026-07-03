<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Cart\CartPricingResult;
use App\DTOs\Cart\CouponApplication;
use App\DTOs\Order\OrderLineSnapshot;
use App\DTOs\Order\OrderTotals;
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

    /**
     * @return list<OrderLineSnapshot>
     */
    public function buildOrderLineSnapshots(Cart $cart): array;

    /**
     * @param  list<OrderLineSnapshot>  $snapshots
     */
    public function calculateOrderTotals(
        array $snapshots,
        ?CouponApplication $coupon,
        Money $shipping,
    ): OrderTotals;
}
