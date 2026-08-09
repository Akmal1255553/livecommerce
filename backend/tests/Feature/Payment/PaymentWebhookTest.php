<?php

declare(strict_types=1);

use App\Contracts\Services\PaymentGatewayInterface;
use App\Enums\SellerPayoutStatus;
use App\Events\PaymentSucceeded;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\SellerPayout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @return array{buyer: array{user_id: string, access_token: string}, product: \App\Models\Product, order_id: string, payment_url: string, transaction_id: string, amount: int}
 */
function checkoutAwaitingPayment(): array
{
    config(['payment.driver' => 'local']);
    app()->forgetInstance(PaymentGatewayInterface::class);
    app()->forgetInstance(\App\Services\Payment\PaymentGatewayResolver::class);

    $data = createSellerProductForCart();
    $buyer = registerUser('paybuyer'.Str::random(4), 'paybuyer'.Str::random(4).'@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk();

    $response = test()->withToken($buyer['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', [
            'cart_version' => 2,
            'payment_method' => 'click',
            'shipping_address' => [
                'full_name' => 'Pay Buyer',
                'phone' => '+998901234567',
                'region' => 'Tashkent',
                'city' => 'Tashkent',
                'address_line' => '1 Pay St',
                'postal_code' => '100000',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'awaiting_payment')
        ->assertJsonStructure(['data' => ['payment_url', 'order' => ['id']]]);

    $orderId = $response->json('data.order.id');
    $order = Order::query()->findOrFail($orderId);

    return [
        'buyer' => $buyer,
        'product' => $data['product'],
        'order_id' => $orderId,
        'payment_url' => $response->json('data.payment_url'),
        'transaction_id' => (string) $order->payment_transaction_id,
        'amount' => (int) $order->total,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postSignedPaymentWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $raw, (string) config('payment.webhook_secret'));

    return test()->call(
        'POST',
        '/api/v1/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ],
        $raw,
    );
}

test('local checkout returns payment url and stays awaiting payment', function () {
    $checkout = checkoutAwaitingPayment();

    expect($checkout['payment_url'])->not->toBeEmpty()
        ->and($checkout['transaction_id'])->toStartWith('local-');

    $reservation = InventoryReservation::query()
        ->where('order_id', $checkout['order_id'])
        ->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->status->value)->toBe('active');
});

test('payment success webhook marks order paid and creates seller payout', function () {
    Event::fake([PaymentSucceeded::class]);
    $checkout = checkoutAwaitingPayment();

    postSignedPaymentWebhook([
        'event' => 'payment.success',
        'transaction_id' => $checkout['transaction_id'],
        'order_id' => $checkout['order_id'],
        'amount' => $checkout['amount'],
        'currency' => 'UZS',
        'timestamp' => now()->toIso8601String(),
    ])->assertOk();

    $order = Order::query()->findOrFail($checkout['order_id']);
    expect($order->status->value)->toBe('paid')
        ->and($order->payment_status->value)->toBe('paid');

    $payout = SellerPayout::query()->where('order_id', $order->id)->first();
    expect($payout)->not->toBeNull()
        ->and($payout->status)->toBe(SellerPayoutStatus::Pending->value)
        ->and($payout->amount)->toBe($checkout['amount']);

    Event::assertDispatched(PaymentSucceeded::class);
});

test('duplicate payment webhook is idempotent', function () {
    $checkout = checkoutAwaitingPayment();

    $payload = [
        'event' => 'payment.success',
        'transaction_id' => $checkout['transaction_id'],
        'order_id' => $checkout['order_id'],
        'amount' => $checkout['amount'],
        'currency' => 'UZS',
    ];

    postSignedPaymentWebhook($payload)->assertOk();
    postSignedPaymentWebhook($payload)->assertOk();

    expect(Order::query()->findOrFail($checkout['order_id'])->status->value)->toBe('paid')
        ->and(SellerPayout::query()->where('order_id', $checkout['order_id'])->count())->toBe(1);
});

test('payment failed webhook cancels order', function () {
    $checkout = checkoutAwaitingPayment();

    postSignedPaymentWebhook([
        'event' => 'payment.failed',
        'transaction_id' => $checkout['transaction_id'],
        'order_id' => $checkout['order_id'],
        'amount' => $checkout['amount'],
        'currency' => 'UZS',
    ])->assertOk();

    $order = Order::query()->findOrFail($checkout['order_id']);
    expect($order->status->value)->toBe('cancelled')
        ->and($order->payment_status->value)->toBe('failed');
});

test('invalid webhook signature is rejected but returns 200', function () {
    $checkout = checkoutAwaitingPayment();

    $payload = [
        'event' => 'payment.success',
        'transaction_id' => $checkout['transaction_id'],
        'order_id' => $checkout['order_id'],
        'amount' => $checkout['amount'],
        'currency' => 'UZS',
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    test()->call(
        'POST',
        '/api/v1/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => 'bad-signature',
        ],
        $raw,
    )->assertOk();

    expect(Order::query()->findOrFail($checkout['order_id'])->status->value)
        ->toBe('awaiting_payment');
});

test('sandbox complete pays awaiting order', function () {
    $checkout = checkoutAwaitingPayment();

    test()->withToken($checkout['buyer']['access_token'])
        ->postJson('/api/v1/payments/sandbox/'.$checkout['order_id'].'/complete', [
            'result' => 'success',
        ])
        ->assertOk()
        ->assertJsonPath('data.order.status', 'paid');
});

test('webhook with mismatched transaction id does not pay order', function () {
    $checkout = checkoutAwaitingPayment();

    postSignedPaymentWebhook([
        'event' => 'payment.success',
        'transaction_id' => 'attacker-txn-id',
        'order_id' => $checkout['order_id'],
        'amount' => $checkout['amount'],
        'currency' => 'UZS',
    ])->assertOk();

    expect(Order::query()->findOrFail($checkout['order_id'])->status->value)
        ->toBe('awaiting_payment');
});

test('sandbox complete returns 404 when disabled', function () {
    config(['payment.sandbox_enabled' => false]);
    $checkout = checkoutAwaitingPayment();

    test()->withToken($checkout['buyer']['access_token'])
        ->postJson('/api/v1/payments/sandbox/'.$checkout['order_id'].'/complete', [
            'result' => 'success',
        ])
        ->assertNotFound();
});
