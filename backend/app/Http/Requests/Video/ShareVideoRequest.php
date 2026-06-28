<?php

declare(strict_types=1);

namespace App\Http\Requests\Video;

use App\Enums\ShareChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareVideoRequest extends FormRequest
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
            'channel' => ['required', 'string', Rule::enum(ShareChannel::class)],
            'session_id' => ['required', 'uuid'],
        ];
    }
}
