<?php

declare(strict_types=1);

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

class AddLiveCartItemRequest extends FormRequest
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
            'product_id' => ['required', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.config('commerce.cart_max_quantity_per_line', 99)],
        ];
    }
}
