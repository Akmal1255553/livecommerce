<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Cart\CartLinePricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartLinePricing */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartLinePricing $line */
        $line = $this->resource;

        return [
            'id' => $line->itemId,
            'product' => new ProductCardResource($line->product),
            'variant' => $line->variant !== null ? [
                'id' => $line->variant->id,
                'name' => $line->variant->name,
                'value' => $line->variant->value,
            ] : null,
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice->toArray(),
            'line_total' => $line->lineTotal->toArray(),
            'discount_amount' => $line->discountAmount->toArray(),
        ];
    }
}
