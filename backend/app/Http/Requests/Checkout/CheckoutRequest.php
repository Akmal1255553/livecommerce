<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            'cart_version' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:32'],
            'shipping_address.region' => ['required', 'string', 'max:120'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.address_line' => ['required', 'string', 'max:500'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
        ];
    }
}
