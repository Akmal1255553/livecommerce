<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Contracts\Services\TaxServiceInterface;
use App\Models\Cart;
use App\ValueObjects\Money;

final class ZeroTaxService implements TaxServiceInterface
{
    public function calculate(Cart $cart): Money
    {
        return Money::zero();
    }
}
