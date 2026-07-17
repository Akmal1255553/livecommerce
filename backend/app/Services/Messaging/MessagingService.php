<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\MessagingServiceInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Block\BlockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessagingService extends BaseService implements MessagingServiceInterface
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly UserRepositoryInterface $users,
        private readonly OrderRepositoryInterface $orders,
        private readonly BlockService $blocks,
    ) {}

    public function listConversations(User $user, ?string $cursor, int $limit): CursorPaginationData
    {
        $beforeId = $cursor !== null && $cursor !== ''
            ? CursorPaginationData::decodeCursorString($cursor)
            : null;

        // Cursor stores conversation UUID as opaque string (not int).
        $beforeId = is_string($cursor) && $cursor !== '' ? base64_decode($cursor, true) ?: $cursor : null;
        if ($beforeId !== null && ! Str::isUuid($beforeId)) {
            $beforeId = null;
        }

        $items = $this->conversations->listForUser($user->id, $beforeId, $limit);
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->take($limit);
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->last()->id)
            : null;

        return new CursorPaginationData($items, $nextCursor, $hasMore, $limit);
    }

    public function createConversation(
        User $actor,
        string $sellerId,
        ?string $orderId = null,
        ?string $initialMessage = null,
    ): Conversation {
        if ($actor->id === $sellerId) {
            throw new ValidationException('Cannot start a conversation with yourself.');
        }

        $seller = $this->users->findById($sellerId);
        if (! $seller instanceof User) {
            throw new ResourceNotFoundException('Seller not found.');
        }

        $seller->loadMissing('store');
        if ($seller->store === null) {
            throw new ValidationException('Target user is not a seller.');
        }

        if ($this->blocks->isBlockedEitherWay($actor->id, $sellerId)) {
            throw new ForbiddenException('You cannot message this user.');
        }

        $order = null;
        if ($orderId !== null) {
            $order = $this->orders->findById($orderId);
            if (! $order instanceof Order) {
                throw new ResourceNotFoundException('Order not found.');
            }
            $this->assertOrderParticipant($actor, $order, $sellerId);
        }

        return DB::transaction(function () use ($actor, $sellerId, $order, $initialMessage): Conversation {
            if ($order !== null) {
                $existing = $this->conversations->findOrderConversation($order->id);
                if ($existing !== null) {
                    return $this->loadConversation($existing->id);
                }
            } else {
                $existing = $this->conversations->findDirectBetween($actor->id, $sellerId);
                if ($existing !== null) {
                    return $this->loadConversation($existing->id);
                }
            }

            $conversation = $this->conversations->create([
                'type' => $order !== null ? ConversationType::Order : ConversationType::Direct,
                'order_id' => $order?->id,
                'created_by' => $actor->id,
            ]);

            $this->conversations->addParticipant($conversation->id, $actor->id);
            $this->conversations->addParticipant($conversation->id, $sellerId);

            if ($initialMessage !== null && trim($initialMessage) !== '') {
                $this->sendMessageInternal($actor, $conversation->id, trim($initialMessage), null);
            }

            return $this->loadConversation($conversation->id);
        });
    }

    public function listMessages(
        User $user,
        string $conversationId,
        ?string $cursor,
        int $limit,
    ): CursorPaginationData {
        $this->requireParticipant($user, $conversationId);

        $beforeId = null;
        if (is_string($cursor) && $cursor !== '') {
            $decoded = base64_decode($cursor, true) ?: $cursor;
            if (Str::isUuid($decoded)) {
                $beforeId = $decoded;
            }
        }

        $items = $this->messages->listForConversation($conversationId, $beforeId, $limit);
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->take($limit);
        }

        // Return chronological for UI (oldest → newest within page).
        $items = $items->reverse()->values();

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->first()->id)
            : null;

        return new CursorPaginationData($items, $nextCursor, $hasMore, $limit);
    }

    public function sendMessage(
        User $user,
        string $conversationId,
        ?string $body = null,
        ?string $imageUrl = null,
    ): Message {
        $this->requireParticipant($user, $conversationId);

        $conversation = $this->loadConversation($conversationId);
        $other = $conversation->participants
            ->first(fn ($p) => $p->user_id !== $user->id);

        if ($other !== null && $this->blocks->isBlockedEitherWay($user->id, $other->user_id)) {
            throw new ForbiddenException('You cannot message this user.');
        }

        return $this->sendMessageInternal($user, $conversationId, $body, $imageUrl);
    }

    public function markRead(User $user, string $conversationId): Conversation
    {
        $this->requireParticipant($user, $conversationId);
        $this->conversations->markRead($conversationId, $user->id);

        return $this->loadConversation($conversationId);
    }

    public function unreadCount(User $user): int
    {
        return $this->conversations->unreadTotal($user->id);
    }

    private function sendMessageInternal(
        User $user,
        string $conversationId,
        ?string $body,
        ?string $imageUrl,
    ): Message {
        $trimmed = $body !== null ? trim($body) : null;
        $hasBody = $trimmed !== null && $trimmed !== '';
        $hasImage = $imageUrl !== null && trim($imageUrl) !== '';

        if (! $hasBody && ! $hasImage) {
            throw new ValidationException('Message body or image_url is required.');
        }

        $type = $hasImage && ! $hasBody ? MessageType::Image : MessageType::Text;
        if ($hasImage && $hasBody) {
            $type = MessageType::Image;
        }

        $now = now();
        $message = $this->messages->create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'type' => $type,
            'body' => $hasBody ? $trimmed : null,
            'image_url' => $hasImage ? trim($imageUrl) : null,
            'created_at' => $now,
        ]);

        $preview = $hasBody ? (string) $trimmed : '[image]';
        $this->conversations->touchLastMessage($conversationId, $preview, $now);
        $this->conversations->incrementUnreadForOthers($conversationId, $user->id);

        $message->load(['sender.profile']);
        event(new MessageSent($message));

        return $message;
    }

    private function requireParticipant(User $user, string $conversationId): void
    {
        $participant = $this->conversations->findParticipant($conversationId, $user->id);
        if ($participant === null) {
            throw new ForbiddenException('You are not a participant of this conversation.');
        }
    }

    private function loadConversation(string $id): Conversation
    {
        $conversation = $this->conversations->findById($id);
        if ($conversation === null) {
            throw new ResourceNotFoundException('Conversation not found.');
        }

        $conversation->load(['participants.user.profile', 'order']);

        return $conversation;
    }

    private function assertOrderParticipant(User $actor, Order $order, string $sellerId): void
    {
        $order->loadMissing('store');
        $storeOwnerId = $order->store?->user_id;

        $isBuyer = $order->user_id === $actor->id;
        $isSeller = $storeOwnerId === $actor->id;

        if (! $isBuyer && ! $isSeller) {
            throw new ForbiddenException('You are not a party to this order.');
        }

        if ($storeOwnerId !== $sellerId && $order->user_id !== $sellerId) {
            throw new ValidationException('seller_id does not match the order parties.');
        }
    }
}
