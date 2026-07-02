<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Cart\CartPricingResult;
use App\DTOs\Cart\CouponApplication;

interface CouponServiceInterface
{
    public function resolve(?string $code, CartPricingResult $pricing): CouponApplication;
}
