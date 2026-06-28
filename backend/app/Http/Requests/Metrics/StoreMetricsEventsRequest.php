<?php

declare(strict_types=1);

namespace App\Http\Requests\Metrics;

use App\Enums\EngagementEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetricsEventsRequest extends FormRequest
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
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*.type' => ['required', 'string', Rule::enum(EngagementEventType::class)],
            'events.*.session_id' => ['required', 'uuid'],
            'events.*.video_id' => ['nullable', 'uuid'],
            'events.*.payload' => ['nullable', 'array'],
        ];
    }
}
