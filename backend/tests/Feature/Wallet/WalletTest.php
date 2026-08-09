<?php

declare(strict_types=1);

use App\Models\Wallet;
use Illuminate\Support\Str;

/**
 * @return array{user_id: string, access_token: string}
 */
function walletUser(): array
{
    $suffix = Str::random(6);

    return registerUser('wallet'.$suffix, 'wallet'.$suffix.'@example.com');
}

/**
 * @param  array{user_id: string, access_token: string}  $user
 */
function fundWallet(array $user, int $amount): void
{
    $transactionId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => $amount, 'method' => 'bitcoin'])
        ->assertCreated()
        ->json('data.transaction.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/wallet/topups/{$transactionId}/confirm", ['success' => true])
        ->assertOk();
}

it('creates an empty wallet on first read', function (): void {
    $user = walletUser();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('data.available.amount', 0)
        ->assertJsonPath('data.held.amount', 0)
        ->assertJsonPath('data.available.currency', 'UZS');

    expect(Wallet::query()->where('user_id', $user['user_id'])->exists())->toBeTrue();
});

it('credits the wallet only after the top-up is confirmed', function (): void {
    $user = walletUser();

    $response = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 500000, 'method' => 'bitcoin'])
        ->assertCreated()
        ->assertJsonPath('data.transaction.status', 'pending');

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 0);

    $transactionId = $response->json('data.transaction.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/wallet/topups/{$transactionId}/confirm", ['success' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.balance_after.amount', 500000);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 500000);
});

it('does not double credit when the top-up is confirmed twice', function (): void {
    $user = walletUser();

    $transactionId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 100000, 'method' => 'bitcoin'])
        ->assertCreated()
        ->json('data.transaction.id');

    foreach (range(1, 2) as $ignored) {
        test()->withToken($user['access_token'])
            ->postJson("/api/v1/wallet/topups/{$transactionId}/confirm", ['success' => true])
            ->assertOk();
    }

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 100000);
});

it('rejects a top-up below the configured minimum', function (): void {
    $user = walletUser();

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/topups', ['amount' => 1, 'method' => 'bitcoin'])
        ->assertStatus(422);
});

it('holds funds while a withdrawal is pending', function (): void {
    config(['wallet.withdrawal.fee_percent' => 1.0]);

    $user = walletUser();
    fundWallet($user, 1000000);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/withdrawals', [
            'amount' => 200000,
            'method' => 'card',
            'card_number' => '8600123412341234',
            'card_holder' => 'TEST USER',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.card_last4', '1234')
        ->assertJsonPath('data.fee.amount', 2000);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 798000)
        ->assertJsonPath('data.held.amount', 202000);
});

it('refuses a withdrawal larger than the available balance', function (): void {
    $user = walletUser();
    fundWallet($user, 100000);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/withdrawals', [
            'amount' => 900000,
            'method' => 'card',
            'card_number' => '8600123412341234',
            'card_holder' => 'TEST USER',
        ])
        ->assertStatus(422);
});

it('returns held funds when a withdrawal is cancelled', function (): void {
    config(['wallet.withdrawal.fee_percent' => 0.0]);

    $user = walletUser();
    fundWallet($user, 1000000);

    $withdrawalId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/wallet/withdrawals', [
            'amount' => 300000,
            'method' => 'card',
            'card_number' => '8600999988887777',
            'card_holder' => 'TEST USER',
        ])
        ->assertCreated()
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/wallet/withdrawals/{$withdrawalId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 1000000)
        ->assertJsonPath('data.held.amount', 0);
});

it('lists wallet transactions newest first', function (): void {
    $user = walletUser();
    fundWallet($user, 100000);
    fundWallet($user, 250000);

    $response = test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet/transactions')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.type'))->toBe('topup')
        ->and($response->json('data.0.direction'))->toBe('credit');
});

it('keeps wallets private to their owner', function (): void {
    test()->getJson('/api/v1/wallet')->assertStatus(401);
});

it('pays an order instantly from the wallet balance', function (): void {
    $data = createSellerProductForCart();
    $user = walletUser();

    // Product is 250000; FixedShippingCalculator defaults to 0 → total 250000.
    fundWallet($user, 500000);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk();

    $response = test()->withToken($user['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', [
            'cart_version' => 2,
            'payment_method' => 'wallet',
            'shipping_address' => [
                'full_name' => 'Wallet Buyer',
                'phone' => '+998901234567',
                'region' => 'Tashkent',
                'city' => 'Tashkent',
                'address_line' => '1 Wallet St',
                'postal_code' => '100000',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'paid')
        ->assertJsonPath('data.order.payment.provider', 'wallet');

    expect($response->json('data.payment_url'))->toBeNull();

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 250000);

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet/transactions')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'order_payment')
        ->assertJsonPath('data.0.direction', 'debit');
});

it('rejects wallet checkout when the balance is too low', function (): void {
    $data = createSellerProductForCart();
    $user = walletUser();

    fundWallet($user, 100000);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk();

    test()->withToken($user['access_token'])
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout', [
            'cart_version' => 2,
            'payment_method' => 'wallet',
            'shipping_address' => [
                'full_name' => 'Wallet Buyer',
                'phone' => '+998901234567',
                'region' => 'Tashkent',
                'city' => 'Tashkent',
                'address_line' => '1 Wallet St',
                'postal_code' => '100000',
            ],
        ])
        ->assertStatus(422);

    // Failed checkout must not spend the balance.
    test()->withToken($user['access_token'])
        ->getJson('/api/v1/wallet')
        ->assertJsonPath('data.available.amount', 100000);
});
