<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LiveChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveChatMessage */
class LiveChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'display_name' => $this->user->profile?->display_name ?? $this->user->username,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
