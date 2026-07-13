<?php

declare(strict_types=1);

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

class SendLiveChatRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:500'],
        ];
    }
}
