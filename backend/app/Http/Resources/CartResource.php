<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Cart\CartViewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartViewData */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartViewData $cart */
        $cart = $this->resource;

        return [
            'id' => $cart->id,
            'type' => $cart->type,
            'version' => $cart->version,
            'items' => CartItemResource::collection($cart->lines),
            'summary' => [
                'subtotal' => $cart->pricing->subtotal->toArray(),
                'discount_total' => $cart->pricing->discountTotal->toArray(),
                'shipping_estimate' => $cart->pricing->shippingEstimate->toArray(),
                'currency' => $cart->pricing->subtotal->currency,
                'item_count' => $cart->pricing->itemCount,
            ],
        ];
    }
}
