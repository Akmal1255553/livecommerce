<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $thumbnail = $this->relationLoaded('images')
            ? $this->images->first()?->url
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'discount_percent' => $this->discountPercent(),
            'thumbnail' => $thumbnail,
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'status' => $this->status->value,
        ];
    }
}
