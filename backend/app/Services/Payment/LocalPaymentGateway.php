<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Sandbox adapter for Uzbekistan gateways (Click / Payme / Uzum).
 * Returns a payment URL; order stays awaiting_payment until webhook / sandbox complete.
 */
class LocalPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        $driver = (string) config('payment.driver', 'local');

        return in_array($driver, ['click', 'payme', 'uzum', 'local'], true)
            ? $driver
            : 'local';
    }

    public function initiate(Order $order): PaymentInitiationResult
    {
        $transactionId = $this->name().'-'.Str::uuid()->toString();

        $paymentUrl = url('/api/v1/payments/sandbox/'.$order->id).'?txn='.urlencode($transactionId);

        return PaymentInitiationResult::succeeded(
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
        );
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $rawPayload,
            (string) config('payment.webhook_secret'),
        );

        return hash_equals($expected, $signatureHeader);
    }
}
