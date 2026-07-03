<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Cart;
use App\ValueObjects\Money;

interface TaxServiceInterface
{
    public function calculate(Cart $cart): Money;
}
