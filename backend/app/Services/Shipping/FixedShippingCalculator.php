<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Services\ShippingCalculatorInterface;
use App\DTOs\Cart\CartPricingResult;
use App\ValueObjects\Money;

class FixedShippingCalculator implements ShippingCalculatorInterface
{
    public function calculate(CartPricingResult $pricing): Money
    {
        if ($pricing->itemCount === 0) {
            return Money::zero();
        }

        return Money::uzs((int) config('commerce.shipping_flat_rate_uzs', 0));
    }
}
