<?php

declare(strict_types=1);

use App\Events\CartCheckedOut;
use App\Events\PaymentSucceeded;
use App\Models\InventoryReservation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function checkoutPayload(int $cartVersion = 2): array
{
    return [
        'cart_version' => $cartVersion,
        'payment_method' => 'fake',
        'shipping_address' => [
            'full_name' => 'Test Buyer',
            'phone' => '+998901234567',
            'region' => 'Tashkent',
            'city' => 'Tashkent',
            'address_line' => '123 Test St',
            'postal_code' => '100000',
        ],
    ];
}

test('checkout creates paid order and clears cart', function () {
    Event::fake([CartCheckedOut::class, PaymentSucceeded::class]);

    $data = createSellerProductForCart();
    $buyer = registerUser('checkoutbuyer', 'checkoutbuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 2,
        ])
        ->assertOk();

    $key = (string) Str::uuid();

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'paid')
        ->assertJsonPath('data.order.items.0.quantity', 2);

    $data['product']->refresh();
    expect($data['product']->stock_quantity)->toBe(48);

    test()->withToken($buyer['access_token'])
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary.item_count', 0);

    Event::assertDispatched(CartCheckedOut::class);
    Event::assertDispatched(PaymentSucceeded::class);
});

test('checkout requires idempotency key', function () {
    $buyer = registerUser('checkoutnokey', 'checkoutnokey@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertStatus(422);
});

test('stale cart version returns 409', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('stalecart', 'stalecart@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', ['product_id' => $data['product']->id, 'quantity' => 1]);

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', checkoutPayload(999))
        ->assertStatus(409);
});

test('duplicate checkout idempotency key returns same order', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('idempcheckout', 'idempcheckout@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', ['product_id' => $data['product']->id, 'quantity' => 1]);

    $key = (string) Str::uuid();
    $first = test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertCreated();

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertCreated()
        ->assertJsonPath('data.order.id', $first->json('data.order.id'));
});

test('checkout rejects empty cart', function () {
    $buyer = registerUser('emptycartco', 'emptycartco@example.com');

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertStatus(422);
});

test('inventory reservation is confirmed after checkout', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('resbuyer', 'resbuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', ['product_id' => $data['product']->id, 'quantity' => 1]);

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', checkoutPayload())
        ->assertCreated();

    expect(InventoryReservation::query()->where('status', 'confirmed')->count())->toBe(1);
});
