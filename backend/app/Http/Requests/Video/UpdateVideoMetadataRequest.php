<?php

declare(strict_types=1);

namespace App\Http\Requests\Video;

use App\Enums\VideoVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoMetadataRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', 'string', Rule::enum(VideoVisibility::class)],
        ];
    }
}
