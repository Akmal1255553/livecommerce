<?php

declare(strict_types=1);

namespace App\Http\Requests\Video;

use App\Enums\VideoVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVideoUploadRequest extends FormRequest
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
        $maxBytes = (int) config('storage.video_max_bytes', 104_857_600);

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', 'string', Rule::enum(VideoVisibility::class)],
            'mime_type' => ['required', 'string', Rule::in(['video/mp4', 'video/quicktime', 'video/webm'])],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ];
    }
}
