<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DTOs\Notification\NotificationData;
use App\Enums\NotificationType;
use App\Events\MessageSent;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\Notification\NotificationService;

class NotifyOnMessageSent
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $message->loadMissing(['sender.profile', 'conversation']);

        $recipientIds = ConversationParticipant::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id');

        $sender = $message->sender;
        if (! $sender instanceof User) {
            return;
        }

        $preview = $message->body ?? '[image]';
        $title = 'New message';
        $body = ($sender->profile?->display_name ?? $sender->username).': '.$preview;

        foreach ($recipientIds as $recipientId) {
            $recipient = User::query()->with('profile')->find($recipientId);
            if (! $recipient instanceof User) {
                continue;
            }

            $this->notifications->notify(
                recipient: $recipient,
                type: NotificationType::NEW_MESSAGE,
                title: $title,
                body: mb_substr($body, 0, 200),
                data: NotificationData::fromUser(
                    user: $sender,
                    entity_type: 'conversation',
                    entity_id: $message->conversation_id,
                    deep_link: "/conversations/{$message->conversation_id}",
                ),
            );
        }
    }
}
