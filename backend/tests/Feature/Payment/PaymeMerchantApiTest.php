<?php

declare(strict_types=1);

use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\WalletServiceInterface;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const PAYME_TEST_KEY = 'payme-merchant-test-key';

beforeEach(function (): void {
    config([
        'payment.payme.merchant_id' => 'merchant-1',
        'payment.payme.secret' => PAYME_TEST_KEY,
        'payment.driver' => 'local',
    ]);
    app()->forgetInstance(PaymentGatewayInterface::class);
    app()->forgetInstance(WalletServiceInterface::class);
    app()->forgetInstance(\App\Services\Payment\PaymentGatewayResolver::class);
});

/**
 * @param  array<string, mixed>  $params
 */
function postPaymeRpc(string $method, array $params, string $key = PAYME_TEST_KEY): TestResponse
{
    return test()
        ->withHeaders(['Authorization' => 'Basic '.base64_encode('Paycom:'.$key)])
        ->postJson('/api/v1/webhooks/payme', [
            'id' => 42,
            'method' => $method,
            'params' => $params,
        ]);
}

/**
 * @return array{user: array{user_id: string, access_token: string}, reference: string, transaction_id: string, tiyin: int}
 */
function pendingPaymeTopUp(int $amount = 150000): array
{
    $suffix = Str::random(6);
    $user = registerUser('payme'.$suffix, 'payme'.$suffix.'@example.com');

    $response = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => $amount, 'method' => 'payme'])
        ->assertCreated();

    $id = $response->json('data.transaction.id');

    return [
        'user' => $user,
        'reference' => 'wt-'.$id,
        'transaction_id' => $id,
        'tiyin' => $amount * 100,
    ];
}

function paymeWalletBalance(string $token): int
{
    return (int) test()->withToken($token)->getJson('/api/v1/wallet')->json('data.available.amount');
}

it('refuses a call that is not authenticated as the merchant', function (): void {
    postPaymeRpc('CheckPerformTransaction', [], 'wrong-key')
        ->assertOk()
        ->assertJsonPath('error.code', -32504);
});

it('allows a charge for a pending top-up', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CheckPerformTransaction', [
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])
        ->assertOk()
        ->assertJsonPath('result.allow', true);
});

it('rejects a charge for an unknown account', function (): void {
    postPaymeRpc('CheckPerformTransaction', [
        'amount' => 100000,
        'account' => ['order_id' => 'wt-'.Str::uuid()],
    ])
        ->assertOk()
        ->assertJsonPath('error.code', -31050);
});

it('rejects a charge whose amount does not match', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CheckPerformTransaction', [
        'amount' => $topUp['tiyin'] + 100,
        'account' => ['order_id' => $topUp['reference']],
    ])
        ->assertOk()
        ->assertJsonPath('error.code', -31001);
});

it('creates a transaction and repeats the same answer for the same payme id', function (): void {
    $topUp = pendingPaymeTopUp();
    $params = [
        'id' => 'payme-txn-1',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ];

    $first = postPaymeRpc('CreateTransaction', $params)
        ->assertOk()
        ->assertJsonPath('result.state', 1);

    $second = postPaymeRpc('CreateTransaction', $params)->assertOk();

    expect($second->json('result.transaction'))->toBe($first->json('result.transaction'))
        ->and($second->json('result.create_time'))->toBe($first->json('result.create_time'));
});

it('refuses a second open transaction for the same account', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-a',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-b',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])
        ->assertOk()
        ->assertJsonPath('error.code', -31008);
});

it('credits the wallet on perform and only once', function (): void {
    $topUp = pendingPaymeTopUp(200000);

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-perform',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();

    expect(paymeWalletBalance($topUp['user']['access_token']))->toBe(0);

    postPaymeRpc('PerformTransaction', ['id' => 'payme-txn-perform'])
        ->assertOk()
        ->assertJsonPath('result.state', 2);

    expect(paymeWalletBalance($topUp['user']['access_token']))->toBe(200000);

    postPaymeRpc('PerformTransaction', ['id' => 'payme-txn-perform'])
        ->assertOk()
        ->assertJsonPath('result.state', 2);

    expect(paymeWalletBalance($topUp['user']['access_token']))->toBe(200000)
        ->and(WalletTransaction::query()->findOrFail($topUp['transaction_id'])->status->value)->toBe('completed');
});

it('reports an unknown transaction as not found', function (): void {
    postPaymeRpc('PerformTransaction', ['id' => 'never-created'])
        ->assertOk()
        ->assertJsonPath('error.code', -31003);
});

it('fails the top-up when payme cancels before performing', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-cancel',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();

    postPaymeRpc('CancelTransaction', ['id' => 'payme-txn-cancel', 'reason' => 3])
        ->assertOk()
        ->assertJsonPath('result.state', -1);

    expect(WalletTransaction::query()->findOrFail($topUp['transaction_id'])->status->value)->toBe('failed')
        ->and(paymeWalletBalance($topUp['user']['access_token']))->toBe(0);
});

it('refuses to cancel money that was already credited', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-settled',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();
    postPaymeRpc('PerformTransaction', ['id' => 'payme-txn-settled'])->assertOk();

    postPaymeRpc('CancelTransaction', ['id' => 'payme-txn-settled', 'reason' => 5])
        ->assertOk()
        ->assertJsonPath('error.code', -31007);

    expect(paymeWalletBalance($topUp['user']['access_token']))->toBe($topUp['tiyin'] / 100);
});

it('reports transaction state back to payme', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-check',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();

    postPaymeRpc('CheckTransaction', ['id' => 'payme-txn-check'])
        ->assertOk()
        ->assertJsonPath('result.state', 1)
        ->assertJsonPath('result.perform_time', 0)
        ->assertJsonPath('result.cancel_time', 0);
});

it('lists transactions in a statement window', function (): void {
    $topUp = pendingPaymeTopUp();

    postPaymeRpc('CreateTransaction', [
        'id' => 'payme-txn-statement',
        'time' => (int) now()->getPreciseTimestamp(3),
        'amount' => $topUp['tiyin'],
        'account' => ['order_id' => $topUp['reference']],
    ])->assertOk();

    postPaymeRpc('GetStatement', [
        'from' => (int) now()->subHour()->getPreciseTimestamp(3),
        'to' => (int) now()->addHour()->getPreciseTimestamp(3),
    ])
        ->assertOk()
        ->assertJsonPath('result.transactions.0.id', 'payme-txn-statement')
        ->assertJsonPath('result.transactions.0.account.order_id', $topUp['reference'])
        ->assertJsonPath('result.transactions.0.amount', $topUp['tiyin']);
});

it('rejects an unknown rpc method', function (): void {
    postPaymeRpc('DoSomethingElse', [])
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});
