<?php

declare(strict_types=1);

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class RecordVideoViewRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
        ];
    }
}
