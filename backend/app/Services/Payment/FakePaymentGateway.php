<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;

/**
 * Instant success — used in Feature tests and PAYMENT_GATEWAY=fake.
 * No redirect URL; CheckoutService marks the order paid immediately.
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        return PaymentInitiationResult::succeeded(
            transactionId: 'fake-'.$intent->reference,
            sandbox: true,
        );
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        // Fake driver must never accept unsigned production webhooks.
        if (app()->environment('production')) {
            return false;
        }

        return true;
    }
}
