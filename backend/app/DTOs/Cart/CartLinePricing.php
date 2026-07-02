<?php

declare(strict_types=1);

namespace App\DTOs\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\ValueObjects\Money;

final readonly class CartLinePricing
{
    public function __construct(
        public string|int $itemId,
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        public Money $discountAmount,
    ) {}
}
