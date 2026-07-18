<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function findById(string $id): ?Conversation
    {
        return Conversation::query()->find($id);
    }

    public function findDirectBetween(string $userA, string $userB): ?Conversation
    {
        return Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB))
            ->first();
    }

    public function findOrderConversation(string $orderId): ?Conversation
    {
        return Conversation::query()
            ->where('order_id', $orderId)
            ->first();
    }

    public function create(array $attributes): Conversation
    {
        return Conversation::query()->create($attributes);
    }

    public function addParticipant(string $conversationId, string $userId): ConversationParticipant
    {
        return ConversationParticipant::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'unread_count' => 0,
        ]);
    }

    public function findParticipant(string $conversationId, string $userId): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();
    }

    public function listForUser(string $userId, ?string $beforeId, int $limit): Collection
    {
        $query = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with([
                'participants.user.profile',
                'order',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $pivot = Conversation::query()->find($beforeId);
            if ($pivot !== null) {
                $query->where(function ($q) use ($pivot): void {
                    $q->where('last_message_at', '<', $pivot->last_message_at)
                        ->orWhere(function ($inner) use ($pivot): void {
                            $inner->where('last_message_at', $pivot->last_message_at)
                                ->where('id', '<', $pivot->id);
                        })
                        ->orWhere(function ($inner) use ($pivot): void {
                            $inner->whereNull('last_message_at')
                                ->where('id', '<', $pivot->id);
                        });
                });
            }
        }

        return $query->limit($limit + 1)->get();
    }

    public function touchLastMessage(string $conversationId, string $preview, DateTimeInterface $at): void
    {
        Conversation::query()->whereKey($conversationId)->update([
            'last_message_preview' => mb_substr($preview, 0, 280),
            'last_message_at' => $at,
        ]);
    }

    public function incrementUnreadForOthers(string $conversationId, string $senderId): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '!=', $senderId)
            ->increment('unread_count');
    }

    public function markRead(string $conversationId, string $userId): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update([
                'unread_count' => 0,
                'last_read_at' => now(),
            ]);
    }

    public function unreadTotal(string $userId): int
    {
        return (int) ConversationParticipant::query()
            ->where('user_id', $userId)
            ->sum('unread_count');
    }
}
