<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Checkout\CheckoutData;
use App\DTOs\Checkout\CheckoutResult;

interface CheckoutServiceInterface
{
    public function checkout(CheckoutData $data): CheckoutResult;
}
