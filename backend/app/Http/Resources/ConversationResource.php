<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;
        $viewerParticipant = $this->participants
            ->first(fn ($p) => $p->user_id === $viewerId);
        $other = $this->participants
            ->first(fn ($p) => $p->user_id !== $viewerId);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'order_id' => $this->order_id,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $viewerParticipant?->unread_count ?? 0,
            'participant' => $other === null ? null : [
                'id' => $other->user?->id,
                'username' => $other->user?->username,
                'display_name' => $other->user?->profile?->display_name
                    ?? $other->user?->username,
                'avatar_url' => $other->user?->avatar_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
