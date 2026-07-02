<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

use App\ValueObjects\Money;

final readonly class CouponApplication
{
    public function __construct(
        public Money $discount,
        public ?string $code = null,
    ) {}

    public static function none(): self
    {
        return new self(Money::zero());
    }
}
