<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Support\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $attributes): Message
    {
        return Message::query()->create($attributes);
    }

    public function listForConversation(string $conversationId, ?string $beforeId, int $limit): Collection
    {
        $query = Message::query()
            ->where('conversation_id', $conversationId)
            ->with(['sender.profile'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $pivot = Message::query()->find($beforeId);
            if ($pivot !== null) {
                $query->where(function ($q) use ($pivot): void {
                    $q->where('created_at', '<', $pivot->created_at)
                        ->orWhere(function ($inner) use ($pivot): void {
                            $inner->where('created_at', $pivot->created_at)
                                ->where('id', '<', $pivot->id);
                        });
                });
            }
        }

        return $query->limit($limit + 1)->get();
    }
}
