<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Pagination\CursorPaginationData;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

interface MessagingServiceInterface
{
    /**
     * @return CursorPaginationData<Conversation>
     */
    public function listConversations(User $user, ?string $cursor, int $limit): CursorPaginationData;

    public function createConversation(
        User $actor,
        string $sellerId,
        ?string $orderId = null,
        ?string $initialMessage = null,
    ): Conversation;

    /**
     * @return CursorPaginationData<Message>
     */
    public function listMessages(
        User $user,
        string $conversationId,
        ?string $cursor,
        int $limit,
    ): CursorPaginationData;

    public function sendMessage(
        User $user,
        string $conversationId,
        ?string $body = null,
        ?string $imageUrl = null,
    ): Message;

    public function markRead(User $user, string $conversationId): Conversation;

    public function unreadCount(User $user): int;
}
