<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
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
        $typeRules = [];

        foreach (NotificationType::cases() as $type) {
            $typeRules["types.{$type->value}"] = ['sometimes', 'boolean'];
        }

        return [
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'types' => ['sometimes', 'array'],
            ...$typeRules,
        ];
    }
}
