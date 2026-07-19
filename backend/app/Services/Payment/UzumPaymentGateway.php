<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\Models\Order;

/**
 * Uzum Bank / Uzum Nasiya style checkout adapter.
 * Without credentials, falls back to local sandbox URL.
 */
class UzumPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'uzum';
    }

    public function initiate(Order $order): PaymentInitiationResult
    {
        $transactionId = 'uzum-pending-'.$order->id;
        $merchantId = (string) config('payment.uzum.merchant_id', '');
        $secret = (string) config('payment.uzum.secret', '');
        $checkoutBase = rtrim((string) config('payment.uzum.checkout_url', 'https://checkout.uzumbank.uz'), '/');

        if ($merchantId === '' || $secret === '') {
            return PaymentInitiationResult::succeeded(
                transactionId: $transactionId,
                paymentUrl: url('/api/v1/payments/sandbox/'.$order->id).'?txn='.urlencode($transactionId),
            );
        }

        $paymentUrl = $checkoutBase.'?'.http_build_query([
            'merchant_id' => $merchantId,
            'amount' => (int) $order->total,
            'order_id' => $order->id,
            'currency' => strtoupper((string) ($order->currency ?? 'UZS')),
            'return_url' => (string) config('payment.uzum.return_url', url('/')),
        ]);

        return PaymentInitiationResult::succeeded(
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
        );
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $secret = (string) config('payment.uzum.secret', '');
        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
