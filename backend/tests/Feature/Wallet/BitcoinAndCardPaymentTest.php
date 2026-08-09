<?php

declare(strict_types=1);

use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\Contracts\Services\WalletServiceInterface;
use App\Models\UserPaymentCard;
use App\Models\WalletTransaction;
use App\Services\Payment\BitcoinPaymentGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @return array{user_id: string, access_token: string}
 */
function btcUser(): array
{
    $suffix = Str::random(6);

    return registerUser('btc'.$suffix, 'btc'.$suffix.'@example.com');
}

function useFreshPaymentSingletons(): void
{
    app()->forgetInstance(PaymentGatewayInterface::class);
    app()->forgetInstance(WalletServiceInterface::class);
    app()->forgetInstance(BitcoinPaymentGateway::class);
    app()->forgetInstance(PaymentWebhookProcessorInterface::class);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postBitcoinWebhook(array $payload, ?string $secret = null): TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $secret ??= (string) config('payment.bitcoin.ipn_secret', 'btc-ipn-secret');
    $sig = hash_hmac('sha512', $raw, $secret);

    return test()->call(
        'POST',
        '/api/v1/webhooks/bitcoin',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_NOWPAYMENTS_SIG' => $sig,
        ],
        $raw,
    );
}

beforeEach(function (): void {
    config([
        'payment.bitcoin.api_key' => '',
        'payment.bitcoin.ipn_secret' => 'btc-ipn-secret',
        'payment.bitcoin.sandbox_rate_uzs_per_btc' => 1_000_000_000,
        'wallet.sandbox_enabled' => true,
    ]);
    useFreshPaymentSingletons();
});

it('initiates a sandbox bitcoin top-up with address and qr payload', function (): void {
    $user = btcUser();

    $response = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 100000, 'method' => 'bitcoin'])
        ->assertCreated()
        ->assertJsonPath('data.transaction.status', 'pending')
        ->assertJsonPath('data.crypto_currency', 'BTC');

    expect($response->json('data.crypto_address'))->not->toBeEmpty()
        ->and($response->json('data.crypto_amount'))->toBe('0.00010000')
        ->and($response->json('data.qr_payload'))->toStartWith('bitcoin:')
        ->and($response->json('data.exchange_rate'))->toBe(1000000000);

    $transaction = WalletTransaction::query()->findOrFail($response->json('data.transaction.id'));
    expect($transaction->metadata['gateway'])->toBe('bitcoin')
        ->and($transaction->metadata['crypto_address'])->not->toBeEmpty();
});

it('returns a live bitcoin quote without creating a top-up', function (): void {
    $user = btcUser();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet/bitcoin-quote?amount=250000')
        ->assertOk()
        ->assertJsonPath('data.crypto_amount', '0.00025000')
        ->assertJsonPath('data.crypto_currency', 'BTC')
        ->assertJsonPath('data.exchange_rate', 1000000000);
});

it('credits the wallet when the bitcoin webhook confirms payment', function (): void {
    $user = btcUser();

    $topUpId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 200000, 'method' => 'bitcoin'])
        ->assertCreated()
        ->json('data.transaction.id');

    postBitcoinWebhook([
        'payment_status' => 'finished',
        'payment_id' => 'np-'.$topUpId,
        'order_id' => 'wt-'.$topUpId,
        // Provider may echo USD invoice amount — ledger amount still comes from our subject.
        'price_amount' => 16.0,
        'price_currency' => 'usd',
    ])->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 200000);

    expect(WalletTransaction::query()->findOrFail($topUpId)->status->value)->toBe('completed');
});

it('stores only the last4 of a card and never the full pan', function (): void {
    $user = btcUser();
    $pan = '8600123456789012';

    $response = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/cards', [
            'card_number' => $pan,
            'holder_name' => 'TEST USER',
            'exp_month' => 12,
            'exp_year' => (int) date('Y') + 2,
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.last4', '9012')
        ->assertJsonPath('data.brand', 'uzcard')
        ->assertJsonMissing(['card_number' => $pan]);

    $card = UserPaymentCard::query()->findOrFail($response->json('data.id'));
    expect($card->last4)->toBe('9012')
        ->and($card->getAttributes())->not->toHaveKey('card_number')
        ->and(json_encode($card->toArray()))->not->toContain($pan);
});

it('top-ups with a saved card and credits the balance via sandbox confirm', function (): void {
    $user = btcUser();

    $cardId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/cards', [
            'card_number' => '4111111111111111',
            'holder_name' => 'CARD HOLDER',
            'exp_month' => 6,
            'exp_year' => (int) date('Y') + 3,
        ])
        ->assertCreated()
        ->json('data.id');

    $topUpId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', [
            'amount' => 150000,
            'method' => 'card',
            'payment_method_id' => $cardId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.transaction.method', 'card')
        ->json('data.transaction.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/wallet/topups/{$topUpId}/confirm", ['success' => true])
        ->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 150000);

    $transaction = WalletTransaction::query()->findOrFail($topUpId);
    expect($transaction->metadata['gateway'])->toBe('card')
        ->and($transaction->metadata['payment_method_id'])->toBe($cardId);
});

it('rejects card top-up without a payment_method_id', function (): void {
    $user = btcUser();

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 100000, 'method' => 'card'])
        ->assertStatus(422);
});

it('lists and deletes saved cards', function (): void {
    $user = btcUser();

    $cardId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/cards', [
            'card_number' => '5555555555554444',
            'holder_name' => 'MC USER',
            'exp_month' => 1,
            'exp_year' => (int) date('Y') + 1,
        ])
        ->assertCreated()
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet/cards')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $cardId);

    test()->withToken($user['access_token'])
        ->deleteJson('/api/v1/wallet/cards/'.$cardId)
        ->assertOk();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet/cards')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
