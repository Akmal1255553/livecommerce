<?php

declare(strict_types=1);

use App\Services\Payment\ClickPaymentGateway;
use App\Services\Payment\PaymePaymentGateway;
use App\Services\Payment\UzumPaymentGateway;
use App\Models\Order;
use Illuminate\Support\Str;

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
    $order = new Order;
    $order->id = (string) Str::uuid();
    $order->total = 50000;
    $order->currency = 'UZS';

    $result = app(ClickPaymentGateway::class)->initiate($order);

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
    $order = new Order;
    $order->id = (string) Str::uuid();
    $order->total = 25000;
    $order->currency = 'UZS';

    $result = app(PaymePaymentGateway::class)->initiate($order);

    expect($result->paymentUrl)->toStartWith('https://checkout.paycom.uz/')
        ->and($result->transactionId)->toStartWith('payme-pending-');
});

test('uzum gateway builds checkout url with hmac-ready secret', function () {
    $order = new Order;
    $order->id = (string) Str::uuid();
    $order->total = 12000;
    $order->currency = 'UZS';

    $gateway = app(UzumPaymentGateway::class);
    $result = $gateway->initiate($order);
    $raw = '{"event":"payment.success","transaction_id":"u1","order_id":"'.$order->id.'","amount":12000,"currency":"UZS"}';
    $sig = hash_hmac('sha256', $raw, 'uzum-secret');

    expect($result->paymentUrl)->toContain('checkout.uzumbank.uz')
        ->and($gateway->verifyWebhookSignature($raw, $sig))->toBeTrue();
});
