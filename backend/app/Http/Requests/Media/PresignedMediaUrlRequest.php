<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresignedMediaUrlRequest extends FormRequest
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
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:1', 'max:104857600'],
            'purpose' => ['required', 'string', Rule::in(['video', 'product_image', 'avatar', 'store_logo'])],
        ];
    }
}
