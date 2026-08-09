<?php

declare(strict_types=1);

use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\WalletServiceInterface;
use App\Models\WalletTransaction;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @return array{user_id: string, access_token: string}
 */
function topUpUser(): array
{
    $suffix = Str::random(6);

    return registerUser('topup'.$suffix, 'topup'.$suffix.'@example.com');
}

/** WalletService resolves the gateway once, so both singletons have to go. */
function usePaymentDriver(string $driver): void
{
    config(['payment.driver' => $driver]);
    app()->forgetInstance(PaymentGatewayInterface::class);
    app()->forgetInstance(PaymentGatewayResolver::class);
    app()->forgetInstance(WalletServiceInterface::class);
}

/**
 * @param  array{user_id: string, access_token: string}  $user
 * @return array{id: string, payment_url: string|null}
 */
function startTopUp(array $user, int $amount): array
{
    $response = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => $amount, 'method' => 'click'])
        ->assertCreated();

    return [
        'id' => $response->json('data.transaction.id'),
        'payment_url' => $response->json('data.payment_url'),
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postTopUpWebhook(array $payload): TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call(
        'POST',
        '/api/v1/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, (string) config('payment.webhook_secret')),
        ],
        $raw,
    );
}

it('sends the top-up to the provider checkout page', function (): void {
    config([
        'payment.click.merchant_id' => '1111',
        'payment.click.service_id' => '2222',
        'payment.click.secret' => 'click-secret',
    ]);
    usePaymentDriver('click');

    $user = topUpUser();
    $topUp = startTopUp($user, 300000);

    expect($topUp['payment_url'])->toContain('my.click.uz/services/pay')
        ->and($topUp['payment_url'])->toContain('transaction_param=wt-'.$topUp['id'])
        ->and($topUp['payment_url'])->toContain('amount=300000.00');

    $transaction = WalletTransaction::query()->findOrFail($topUp['id']);
    expect($transaction->metadata['gateway'])->toBe('click')
        ->and($transaction->status->value)->toBe('pending');
});

it('credits the wallet only when the provider confirms', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 250000);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 0);

    postTopUpWebhook([
        'event' => 'payment.success',
        'transaction_id' => 'click-txn-1',
        'order_id' => 'wt-'.$topUp['id'],
        'amount' => 250000,
        'currency' => 'UZS',
    ])->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 250000);

    $transaction = WalletTransaction::query()->findOrFail($topUp['id']);
    expect($transaction->status->value)->toBe('completed')
        ->and($transaction->metadata['gateway_transaction_id'])->toBe('click-txn-1');
});

it('does not credit twice for a repeated provider callback', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 100000);

    $payload = [
        'event' => 'payment.success',
        'transaction_id' => 'click-txn-2',
        'order_id' => 'wt-'.$topUp['id'],
        'amount' => 100000,
        'currency' => 'UZS',
    ];

    postTopUpWebhook($payload)->assertOk();
    postTopUpWebhook($payload)->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 100000);
});

it('ignores a callback that claims a different amount', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 100000);

    postTopUpWebhook([
        'event' => 'payment.success',
        'transaction_id' => 'click-txn-3',
        'order_id' => 'wt-'.$topUp['id'],
        'amount' => 9000000,
        'currency' => 'UZS',
    ])->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 0);
});

it('rejects an unsigned callback', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 100000);

    $raw = json_encode([
        'event' => 'payment.success',
        'transaction_id' => 'click-txn-4',
        'order_id' => 'wt-'.$topUp['id'],
        'amount' => 100000,
        'currency' => 'UZS',
    ], JSON_THROW_ON_ERROR);

    test()->call(
        'POST',
        '/api/v1/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => 'forged',
        ],
        $raw,
    )->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 0);
});

it('marks the top-up failed when the provider declines', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 100000);

    postTopUpWebhook([
        'event' => 'payment.failed',
        'transaction_id' => 'click-txn-5',
        'order_id' => 'wt-'.$topUp['id'],
        'amount' => 100000,
        'currency' => 'UZS',
    ])->assertOk();

    expect(WalletTransaction::query()->findOrFail($topUp['id'])->status->value)->toBe('failed');

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 0);
});

it('refuses to start a top-up when no provider is configured', function (): void {
    config(['wallet.sandbox_enabled' => false]);
    usePaymentDriver('local');

    $user = topUpUser();

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 100000, 'method' => 'click'])
        ->assertStatus(422);
});

it('hides sandbox confirmation outside sandbox', function (): void {
    usePaymentDriver('local');

    $user = topUpUser();
    $topUp = startTopUp($user, 100000);

    config(['wallet.sandbox_enabled' => false]);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups/'.$topUp['id'].'/confirm', ['success' => true])
        ->assertNotFound();
});
