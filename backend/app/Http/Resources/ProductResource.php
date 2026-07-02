<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store' => new StoreCompactResource($this->whenLoaded('store')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'discount_percent' => $this->discountPercent(),
            'currency' => 'UZS',
            'sku' => $this->sku,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->status->value,
            'rating_avg' => (float) $this->rating_avg,
            'review_count' => $this->review_count,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'sort_order' => $image->sort_order,
            ])->values()->all()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'value' => $variant->value,
                'sku' => $variant->sku,
                'price_adjustment' => (float) $variant->price_adjustment,
                'stock_quantity' => $variant->stock_quantity,
            ])->values()->all()),
            'is_favorited' => (bool) ($this->is_favorited ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
