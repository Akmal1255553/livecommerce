<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\ValueObjects\Money;

final readonly class OrderLineSnapshot
{
    public function __construct(
        public string $productId,
        public ?int $variantId,
        public string $productTitle,
        public ?string $variantName,
        public ?string $sku,
        public int $quantity,
        public Money $unitPrice,
        public Money $discount,
        public Money $lineTotal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toItemAttributes(string $orderId): array
    {
        return [
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'product_title' => $this->productTitle,
            'variant_name' => $this->variantName,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->amount,
            'discount' => $this->discount->amount,
            'line_total' => $this->lineTotal->amount,
            'currency' => $this->unitPrice->currency,
        ];
    }
}
