<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Cart\CartPricingResult;
use App\ValueObjects\Money;

interface ShippingCalculatorInterface
{
    public function calculate(CartPricingResult $pricing): Money;
}
