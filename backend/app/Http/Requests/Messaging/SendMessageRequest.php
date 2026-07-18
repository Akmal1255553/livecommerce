<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $body = $this->input('body');
            $image = $this->input('image_url');
            if (($body === null || trim((string) $body) === '') && ($image === null || trim((string) $image) === '')) {
                $validator->errors()->add('body', 'Provide body or image_url.');
            }
        });
    }
}
