<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VideoProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoProduct */
class VideoProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product' => new ProductCardResource($this->product),
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'product_version' => $this->product_version,
        ];
    }
}
