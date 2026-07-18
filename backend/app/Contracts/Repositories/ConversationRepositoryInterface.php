<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Support\Collection;

interface ConversationRepositoryInterface
{
    public function findById(string $id): ?Conversation;

    public function findDirectBetween(string $userA, string $userB): ?Conversation;

    public function findOrderConversation(string $orderId): ?Conversation;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Conversation;

    public function addParticipant(string $conversationId, string $userId): ConversationParticipant;

    public function findParticipant(string $conversationId, string $userId): ?ConversationParticipant;

    /**
     * @return Collection<int, Conversation>
     */
    public function listForUser(string $userId, ?string $beforeId, int $limit): Collection;

    public function touchLastMessage(string $conversationId, string $preview, \DateTimeInterface $at): void;

    public function incrementUnreadForOthers(string $conversationId, string $senderId): void;

    public function markRead(string $conversationId, string $userId): void;

    public function unreadTotal(string $userId): int;
}
