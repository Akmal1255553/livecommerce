<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

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
            'method' => ['required', 'string', 'in:click,payme,uzum,card,local'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
