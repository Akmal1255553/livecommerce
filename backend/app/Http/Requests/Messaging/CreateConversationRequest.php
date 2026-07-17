<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
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
            'seller_id' => ['required', 'uuid', 'exists:users,id'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
