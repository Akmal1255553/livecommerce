<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_title' => $this->product_title,
            'variant_name' => $this->variant_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => Money::uzs((int) $this->unit_price)->toArray(),
            'discount_amount' => Money::uzs((int) $this->discount)->toArray(),
            'line_total' => Money::uzs((int) $this->line_total)->toArray(),
            'product' => $this->whenLoaded('product', fn () => new ProductCardResource($this->product)),
        ];
    }
}
