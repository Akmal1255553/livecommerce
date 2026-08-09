<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentCardRequest extends FormRequest
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
            'card_number' => ['required', 'string', 'regex:/^\d{12,19}$/'],
            'holder_name' => ['required', 'string', 'min:3', 'max:255'],
            'exp_month' => ['required', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['required', 'integer', 'min:'.(int) date('Y'), 'max:'.((int) date('Y') + 20)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('card_number')) {
            $this->merge([
                'card_number' => preg_replace('/\D+/', '', (string) $this->input('card_number')),
            ]);
        }
    }
}
