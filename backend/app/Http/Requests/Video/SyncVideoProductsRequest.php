<?php

declare(strict_types=1);

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class SyncVideoProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'products' => ['present', 'array', 'max:'.config('commerce.video_max_products', 20)],
            'products.*.product_id' => ['required', 'uuid'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:32767'],
            'products.*.is_featured' => ['sometimes', 'boolean'],
            'products.*.starts_at' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products.*.ends_at' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products.*.position_x' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'products.*.position_y' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
