<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Contracts\Services\CouponServiceInterface;
use App\DTOs\Cart\CartPricingResult;
use App\DTOs\Cart\CouponApplication;

class NoDiscountCouponService implements CouponServiceInterface
{
    public function resolve(?string $code, CartPricingResult $pricing): CouponApplication
    {
        return CouponApplication::none();
    }
}
