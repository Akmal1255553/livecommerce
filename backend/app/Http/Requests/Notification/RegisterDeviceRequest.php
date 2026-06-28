<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
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
            'fcm_token' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'device_id' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
