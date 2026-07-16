<?php

declare(strict_types=1);

use App\Enums\LiveAnalyticsEventType;
use App\Enums\LiveChatMessageType;
use App\Enums\LiveSessionStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\LiveAnalyticsEvent;
use App\Models\LiveChatMessage;
use App\Models\LiveSession;
use App\Models\Product;

test('seller can start end pin and chat on live session', function () {
    $seller = createSellerWithStore();
    $category = Category::factory()->create();
    $product = Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
        'price' => 50000,
    ]);

    $start = test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', [
            'title' => 'Friday Live Sale',
            'product_ids' => [$product->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Friday Live Sale')
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.publisher_token', fn ($v) => is_string($v) && $v !== '');

    $sessionId = $start->json('data.id');

    expect(LiveAnalyticsEvent::query()
        ->where('live_session_id', $sessionId)
        ->where('event_type', LiveAnalyticsEventType::LiveStarted)
        ->exists())->toBeTrue();

    test()->withToken($seller['token'])
        ->postJson("/api/v1/live/{$sessionId}/pin-product", [
            'product_id' => $product->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.pinned_products.0.product_id', $product->id)
        ->assertJsonPath('data.pinned_products.0.offset_seconds', fn ($v) => is_int($v));

    expect(LiveChatMessage::query()
        ->where('live_session_id', $sessionId)
        ->where('type', LiveChatMessageType::System)
        ->exists())->toBeTrue();

    $buyer = registerUser('livebuyer', 'livebuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson("/api/v1/live/{$sessionId}/join")
        ->assertOk()
        ->assertJsonPath('data.current_viewers', 1)
        ->assertJsonPath('data.unique_viewers', 1);

    test()->withToken($buyer['access_token'])
        ->postJson("/api/v1/live/{$sessionId}/chat", [
            'message' => 'How much is shipping?',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'user')
        ->assertJsonPath('data.message', 'How much is shipping?');

    test()->withToken($buyer['access_token'])
        ->postJson("/api/v1/live/{$sessionId}/add-to-cart", [
            'product_id' => $product->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.cart.summary.item_count', 1)
        ->assertJsonPath('data.chat_message.type', 'commerce')
        ->assertJsonPath('data.chat_message.metadata.product_id', $product->id);

    expect(LiveAnalyticsEvent::query()
        ->where('live_session_id', $sessionId)
        ->where('event_type', LiveAnalyticsEventType::ProductAddedToCart)
        ->exists())->toBeTrue();

    expect(LiveChatMessage::query()
        ->where('live_session_id', $sessionId)
        ->where('type', LiveChatMessageType::Commerce)
        ->exists())->toBeTrue();

    test()->getJson("/api/v1/live/{$sessionId}/chat")
        ->assertOk()
        ->assertJsonCount(3, 'data');

    test()->withToken($seller['token'])
        ->postJson("/api/v1/live/{$sessionId}/end")
        ->assertOk()
        ->assertJsonPath('data.status', LiveSessionStatus::Ended->value);

    expect(LiveSession::query()->findOrFail($sessionId)->status)->toBe(LiveSessionStatus::Ended);
});

test('pin product enforces max of three', function () {
    $seller = createSellerWithStore();
    $category = Category::factory()->create();

    $products = collect(range(1, 4))->map(fn (int $i) => Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'title' => "Product {$i}",
        'price' => 10000 * $i,
    ]));

    $sessionId = test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', ['title' => 'Pin Limit Live'])
        ->assertCreated()
        ->json('data.id');

    foreach ($products->take(3) as $product) {
        test()->withToken($seller['token'])
            ->postJson("/api/v1/live/{$sessionId}/pin-product", [
                'product_id' => $product->id,
            ])
            ->assertOk();
    }

    test()->withToken($seller['token'])
        ->postJson("/api/v1/live/{$sessionId}/pin-product", [
            'product_id' => $products[3]->id,
        ])
        ->assertStatus(409);
});

test('buyer cannot start live and discover returns content items', function () {
    $buyer = registerUser('nodash', 'nodash@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/live/start', ['title' => 'Nope'])
        ->assertForbidden();

    $seller = createSellerWithStore();
    test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', ['title' => 'Discover Me'])
        ->assertCreated();

    test()->getJson('/api/v1/live')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Discover Me');

    $discover = test()->getJson('/api/v1/discover?limit=20')
        ->assertOk();

    $types = collect($discover->json('data'))->pluck('type')->all();
    expect($types)->toContain('live');
});

test('seller cannot start two concurrent live sessions', function () {
    $seller = createSellerWithStore();

    test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', ['title' => 'First'])
        ->assertCreated();

    test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', ['title' => 'Second'])
        ->assertStatus(409);
});

test('buyer cannot add unpinned product to cart from live', function () {
    $seller = createSellerWithStore();
    $category = Category::factory()->create();
    $product = Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    $sessionId = test()->withToken($seller['token'])
        ->postJson('/api/v1/live/start', [
            'title' => 'Unpinned Only',
            'product_ids' => [$product->id],
        ])
        ->assertCreated()
        ->json('data.id');

    $buyer = registerUser('unpinnedbuyer', 'unpinnedbuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson("/api/v1/live/{$sessionId}/add-to-cart", [
            'product_id' => $product->id,
        ])
        ->assertNotFound();
});
