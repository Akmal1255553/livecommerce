<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\ValueObjects\Money;

final readonly class OrderTotals
{
    public function __construct(
        public Money $subtotal,
        public Money $shipping,
        public Money $discount,
        public Money $tax,
        public Money $total,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toOrderAttributes(): array
    {
        return [
            'subtotal' => $this->subtotal->amount,
            'shipping_cost' => $this->shipping->amount,
            'discount' => $this->discount->amount,
            'tax' => $this->tax->amount,
            'total' => $this->total->amount,
            'currency' => $this->total->currency,
        ];
    }
}
