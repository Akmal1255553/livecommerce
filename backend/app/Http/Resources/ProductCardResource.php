<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Product\ProductCardData;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product|ProductCardData
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $card = $this->resource instanceof ProductCardData
            ? $this->resource
            : ProductCardData::fromProduct($this->resource);

        return [
            'id' => $card->id,
            'title' => $card->title,
            'price' => $card->price,
            'compare_at_price' => $card->compareAtPrice,
            'discount_percent' => $card->discountPercent,
            'currency' => $card->currency,
            'thumbnail' => $card->thumbnail,
            'store_name' => $card->storeName,
            'status' => $card->status,
            'is_purchasable' => $card->isPurchasable,
        ];
    }
}
