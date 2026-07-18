<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'sender' => [
                'id' => $this->sender?->id,
                'username' => $this->sender?->username,
                'display_name' => $this->sender?->profile?->display_name
                    ?? $this->sender?->username,
                'avatar_url' => $this->sender?->avatar_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
