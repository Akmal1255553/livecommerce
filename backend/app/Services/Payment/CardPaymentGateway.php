<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;
use Illuminate\Support\Str;

/**
 * Sandbox card charge for wallet top-up / checkout.
 * Production will bind a PSP token (Click/Payme); MVP settles via sandbox confirm.
 */
class CardPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'card';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $transactionId = 'card-'.Str::uuid()->toString();

        // No external checkout: mobile (or sandbox confirm) settles the charge.
        return PaymentInitiationResult::succeeded(
            transactionId: $transactionId,
            paymentUrl: $intent->sandboxUrlFor($transactionId),
            sandbox: true,
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
