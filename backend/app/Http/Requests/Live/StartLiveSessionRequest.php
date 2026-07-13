<?php

declare(strict_types=1);

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

class StartLiveSessionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'product_ids' => ['sometimes', 'array', 'max:20'],
            'product_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
