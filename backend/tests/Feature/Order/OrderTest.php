<?php

declare(strict_types=1);

use App\Contracts\Services\OrderServiceInterface;
use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\OrderLineSnapshot;
use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PaymentSnapshot;
use App\DTOs\Order\ShipmentSnapshot;
use App\Enums\OrderStatus;
use App\Models\Product;
use App\ValueObjects\Money;
use Illuminate\Support\Str;

test('createFromCheckout persists immutable line snapshots', function () {
    $data = createPaidOrderForBuyer();

    expect($data['order']->status)->toBe(OrderStatus::Paid)
        ->and($data['order']->items)->toHaveCount(1)
        ->and($data['order']->items->first()->product_title)->toBe($data['product']->title)
        ->and($data['order']->order_number)->toMatch('/^LC-/');
});

test('buyer can list own orders', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('data.0.order_number', $data['order']->order_number);
});

test('buyer cannot view another user order', function () {
    $data = createPaidOrderForBuyer();
    $other = registerUser('otherbuyer', 'otherbuyer@example.com');

    test()->withToken($other['access_token'])
        ->getJson('/api/v1/orders/'.$data['order']->id)
        ->assertNotFound();
});

test('buyer can cancel pending order', function () {
    $seller = createSellerWithStore();
    $product = Product::factory()->for($seller['store'])->create(['stock_quantity' => 10]);
    $buyer = registerUser('cancelbuyer', 'cancelbuyer@example.com');
    $orderService = app(OrderServiceInterface::class);

    $unitPrice = Money::uzs(100000);
    $order = $orderService->createFromCheckout(new CreateOrderData(
        userId: $buyer['user_id'],
        storeId: $seller['store']->id,
        lines: [new OrderLineSnapshot(
            productId: $product->id,
            variantId: null,
            productTitle: $product->title,
            variantName: null,
            sku: $product->sku,
            quantity: 1,
            unitPrice: $unitPrice,
            discount: Money::zero(),
            lineTotal: $unitPrice,
        )],
        totals: new OrderTotals(
            subtotal: $unitPrice,
            shipping: Money::zero(),
            discount: Money::zero(),
            tax: Money::zero(),
            total: $unitPrice,
        ),
        payment: new PaymentSnapshot('fake', 'fake', null, $unitPrice, 'pending'),
        shipment: new ShipmentSnapshot(['full_name' => 'A', 'phone' => '1', 'region' => 'R', 'city' => 'C', 'address_line' => 'L', 'postal_code' => '1']),
    ));

    test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/orders/'.$order->id.'/cancel', ['reason' => 'Changed mind'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('buyer cannot cancel paid order', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->postJson('/api/v1/orders/'.$data['order']->id.'/cancel')
        ->assertStatus(422);
});

test('order detail includes timeline and version', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->getJson('/api/v1/orders/'.$data['order']->id)
        ->assertOk()
        ->assertJsonStructure(['data' => ['version', 'timeline', 'totals', 'payment', 'shipment']]);
});

test('duplicate cancel is idempotent', function () {
    $seller = createSellerWithStore();
    $product = Product::factory()->for($seller['store'])->create();
    $buyer = registerUser('idempbuyer', 'idempbuyer@example.com');
    $orderService = app(OrderServiceInterface::class);
    $unitPrice = Money::uzs(100000);
    $order = $orderService->createFromCheckout(new CreateOrderData(
        userId: $buyer['user_id'],
        storeId: $seller['store']->id,
        lines: [new OrderLineSnapshot($product->id, null, $product->title, null, $product->sku, 1, $unitPrice, Money::zero(), $unitPrice)],
        totals: new OrderTotals($unitPrice, Money::zero(), Money::zero(), Money::zero(), $unitPrice),
        payment: new PaymentSnapshot('fake', 'fake', null, $unitPrice, 'pending'),
        shipment: new ShipmentSnapshot(['full_name' => 'A', 'phone' => '1', 'region' => 'R', 'city' => 'C', 'address_line' => 'L', 'postal_code' => '1']),
    ));
    $key = (string) Str::uuid();

    test()->withToken($buyer['access_token'])->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/orders/'.$order->id.'/cancel')->assertOk();
    test()->withToken($buyer['access_token'])->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/orders/'.$order->id.'/cancel')->assertOk();
});

test('concurrent status update returns 409 on version mismatch', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['seller']['token'])
        ->withHeader('If-Match', '999')
        ->putJson('/api/v1/seller/orders/'.$data['order']->id.'/status', ['status' => 'packing'])
        ->assertStatus(409);
});
