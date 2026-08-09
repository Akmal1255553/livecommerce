<?php

declare(strict_types=1);

use App\DTOs\Payment\PaymentIntent;
use App\Models\Order;
use App\Services\Payment\BitcoinPaymentGateway;
use App\Services\Payment\CardPaymentGateway;
use App\Services\Payment\ClickPaymentGateway;
use App\Services\Payment\PaymePaymentGateway;
use App\Services\Payment\UzumPaymentGateway;
use Illuminate\Support\Str;

function orderIntent(int $total): PaymentIntent
{
    $order = new Order;
    $order->id = (string) Str::uuid();
    $order->total = $total;
    $order->currency = 'UZS';

    return PaymentIntent::forOrder($order);
}

beforeEach(function () {
    config([
        'payment.click.merchant_id' => '1111',
        'payment.click.service_id' => '2222',
        'payment.click.secret' => 'test-click-secret',
        'payment.click.merchant_user_id' => '3333',
        'payment.click.return_url' => 'https://app.example/return',
        'payment.payme.merchant_id' => 'payme-merchant',
        'payment.payme.secret' => 'payme-secret',
        'payment.uzum.merchant_id' => 'uzum-merchant',
        'payment.uzum.secret' => 'uzum-secret',
        'payment.uzum.checkout_url' => 'https://checkout.uzumbank.uz',
        'payment.uzum.return_url' => 'https://app.example/return',
    ]);
});

test('click gateway builds my.click.uz payment url', function () {
    $result = app(ClickPaymentGateway::class)->initiate(orderIntent(50000));

    expect($result->success)->toBeTrue()
        ->and($result->transactionId)->toStartWith('click-pending-')
        ->and($result->paymentUrl)->toContain('my.click.uz/services/pay')
        ->and($result->paymentUrl)->toContain('service_id=2222')
        ->and($result->paymentUrl)->toContain('merchant_id=1111');
});

test('click sign verification accepts prepare digest', function () {
    $secret = 'test-click-secret';
    $payload = [
        'click_trans_id' => '99',
        'service_id' => '2222',
        'merchant_trans_id' => 'order-1',
        'amount' => '1000.00',
        'action' => '0',
        'sign_time' => '2026-07-19 12:00:00',
    ];
    $payload['sign_string'] = md5(
        $payload['click_trans_id']
        .$payload['service_id']
        .$secret
        .$payload['merchant_trans_id']
        .$payload['amount']
        .$payload['action']
        .$payload['sign_time']
    );

    expect(app(ClickPaymentGateway::class)->verifyClickSign($payload))->toBeTrue();
});

test('payme gateway builds checkout.paycom.uz url', function () {
    $result = app(PaymePaymentGateway::class)->initiate(orderIntent(25000));

    expect($result->paymentUrl)->toStartWith('https://checkout.paycom.uz/')
        ->and($result->transactionId)->toStartWith('payme-pending-');
});

test('uzum gateway builds checkout url with hmac-ready secret', function () {
    $intent = orderIntent(12000);
    $gateway = app(UzumPaymentGateway::class);
    $result = $gateway->initiate($intent);
    $raw = '{"event":"payment.success","transaction_id":"u1","order_id":"'.$intent->reference.'","amount":12000,"currency":"UZS"}';
    $sig = hash_hmac('sha256', $raw, 'uzum-secret');

    expect($result->paymentUrl)->toContain('checkout.uzumbank.uz')
        ->and($gateway->verifyWebhookSignature($raw, $sig))->toBeTrue();
});

test('bitcoin sandbox initiate returns crypto fields without api key', function () {
    config([
        'payment.bitcoin.api_key' => '',
        'payment.bitcoin.sandbox_rate_uzs_per_btc' => 2_000_000_000,
        'payment.bitcoin.sandbox_address' => 'bc1qtestaddress',
    ]);

    $result = app(BitcoinPaymentGateway::class)->initiate(orderIntent(100000));

    expect($result->success)->toBeTrue()
        ->and($result->sandbox)->toBeTrue()
        ->and($result->cryptoAddress)->toBe('bc1qtestaddress')
        ->and($result->cryptoAmount)->toBe('0.00005000')
        ->and($result->cryptoCurrency)->toBe('BTC')
        ->and($result->qrPayload)->toBe('bitcoin:bc1qtestaddress?amount=0.00005000');
});

test('card gateway returns sandbox confirm url', function () {
    $result = app(CardPaymentGateway::class)->initiate(orderIntent(50000));

    expect($result->success)->toBeTrue()
        ->and($result->sandbox)->toBeTrue()
        ->and($result->transactionId)->toStartWith('card-')
        ->and($result->paymentUrl)->toContain('/payments/sandbox/');
});
