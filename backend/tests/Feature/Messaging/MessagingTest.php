<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Support\Facades\Queue;

test('buyer can create conversation and exchange messages with seller', function () {
    Queue::fake();

    $buyer = registerUser('msgbuyer', 'msgbuyer@example.com');
    $seller = createSellerWithStore();

    $create = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/conversations', [
            'seller_id' => $seller['user']->id,
            'message' => 'Hi, is this available?',
        ])
        ->assertCreated()
        ->assertJsonPath('data.participant.id', $seller['user']->id)
        ->assertJsonPath('data.type', 'direct');

    $conversationId = $create->json('data.id');

    test()->withToken($seller['token'])
        ->getJson("/api/v1/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Hi, is this available?');

    test()->withToken($seller['token'])
        ->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Yes, in stock!',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Yes, in stock!');

    test()->withToken($buyer['access_token'])
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $conversationId);

    test()->withToken($buyer['access_token'])
        ->getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    test()->withToken($buyer['access_token'])
        ->putJson("/api/v1/conversations/{$conversationId}/read")
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    test()->assertDatabaseHas('notifications', [
        'user_id' => $seller['user']->id,
        'type' => NotificationType::NEW_MESSAGE->value,
    ]);

    Queue::assertPushed(SendPushNotificationJob::class);
});

test('creating conversation twice returns the same direct thread', function () {
    $buyer = registerUser('msgbuyer2', 'msgbuyer2@example.com');
    $seller = createSellerWithStore();

    $first = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/conversations', [
            'seller_id' => $seller['user']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $second = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/conversations', [
            'seller_id' => $seller['user']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    expect($first)->toBe($second);
});

test('blocked users cannot send messages', function () {
    $buyer = registerUser('msgblocked', 'msgblocked@example.com');
    $seller = createSellerWithStore();

    $conversationId = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/conversations', [
            'seller_id' => $seller['user']->id,
            'message' => 'Hello',
        ])
        ->assertCreated()
        ->json('data.id');

    test()->withToken($seller['token'])
        ->postJson("/api/v1/users/{$buyer['user_id']}/block")
        ->assertCreated();

    test()->withToken($buyer['access_token'])
        ->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Still there?',
        ])
        ->assertForbidden();

    test()->withToken($seller['token'])
        ->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Nope',
        ])
        ->assertForbidden();
});

test('order-linked conversation can be created by buyer', function () {
    $fixture = createPaidOrderForBuyer();

    test()->withToken($fixture['buyer_token'])
        ->postJson('/api/v1/conversations', [
            'seller_id' => $fixture['seller']['user']->id,
            'order_id' => $fixture['order']->id,
            'message' => 'About my order',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'order')
        ->assertJsonPath('data.order_id', $fixture['order']->id);
});
