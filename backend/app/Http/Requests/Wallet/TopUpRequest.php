<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopUpRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:bitcoin,card,click,payme,uzum,local'],
            'payment_method_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn (): bool => $this->input('method') === 'card'),
            ],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
