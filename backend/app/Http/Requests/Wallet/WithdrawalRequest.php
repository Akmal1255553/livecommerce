<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
            'method' => ['required', 'string', 'in:card,bank'],
            'card_number' => ['required', 'string', 'min:12', 'max:24'],
            'card_holder' => ['required', 'string', 'max:120'],
        ];
    }
}
