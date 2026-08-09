<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;

/**
 * Payme.uz Merchant API adapter (checkout URL + Basic-auth webhooks).
 * Without credentials, falls back to local sandbox URL.
 */
class PaymePaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'payme';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $transactionId = 'payme-pending-'.$intent->reference;
        $merchantId = (string) config('payment.payme.merchant_id', '');
        $secret = (string) config('payment.payme.secret', '');

        if ($merchantId === '' || $secret === '') {
            return PaymentInitiationResult::succeeded(
                transactionId: $transactionId,
                paymentUrl: $intent->sandboxUrlFor($transactionId),
                sandbox: true,
            );
        }

        // Payme amount is in tiyin (1 UZS = 100 tiyin).
        $amountTiyin = $intent->amount * 100;
        $params = base64_encode(http_build_query([
            'm' => $merchantId,
            'ac.order_id' => $intent->reference,
            'a' => $amountTiyin,
        ]));

        $paymentUrl = 'https://checkout.paycom.uz/'.$params;

        return PaymentInitiationResult::succeeded(
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
        );
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        // Payme uses HTTP Basic Auth (Paycom:{secret}), verified in PaymeWebhookController.
        return $signatureHeader !== null && $signatureHeader !== '';
    }

    public function verifyBasicAuth(?string $authorizationHeader): bool
    {
        $secret = (string) config('payment.payme.secret', '');
        if ($secret === '' || $authorizationHeader === null || ! str_starts_with($authorizationHeader, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($authorizationHeader, 6), true);
        if ($decoded === false) {
            return false;
        }

        return hash_equals('Paycom:'.$secret, $decoded);
    }
}
