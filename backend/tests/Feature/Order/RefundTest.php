<?php

declare(strict_types=1);

use App\Models\RefundRequest;
use Illuminate\Support\Str;

test('buyer requests refund on paid order', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'Product not as described'])
        ->assertOk()
        ->assertJsonPath('data.status', 'refund_requested');
});

test('seller approves refund to refunded', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'Damaged item']);

    $refund = RefundRequest::query()->where('order_id', $data['order']->id)->first();

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/refunds/'.$refund->id, ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');
});

test('seller rejects refund restores status', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['buyer_token'])
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'Changed mind']);

    $refund = RefundRequest::query()->where('order_id', $data['order']->id)->first();

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/refunds/'.$refund->id, ['action' => 'reject'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');
});

test('refund outside window returns 422', function () {
    $data = createPaidOrderForBuyer();
    $data['order']->update(['paid_at' => now()->subDays(30)]);

    test()->withToken($data['buyer_token'])
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'Too late'])
        ->assertUnprocessable();
});

test('duplicate refund request idempotent', function () {
    $data = createPaidOrderForBuyer();
    $key = (string) Str::uuid();

    test()->withToken($data['buyer_token'])->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'First'])->assertOk();
    test()->withToken($data['buyer_token'])->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/orders/'.$data['order']->id.'/refund', ['reason' => 'First'])->assertOk();
});
