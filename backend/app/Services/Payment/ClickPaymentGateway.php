<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;
use Illuminate\Support\Str;

/**
 * Click.uz Shop API adapter — payment URL + MD5 webhook verification.
 * Without merchant credentials, falls back to local sandbox URL.
 */
class ClickPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'click';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $transactionId = $this->pendingTransactionId($intent->reference);
        $merchantId = (string) config('payment.click.merchant_id', '');
        $serviceId = (string) config('payment.click.service_id', '');
        $secret = (string) config('payment.click.secret', '');

        if ($merchantId === '' || $serviceId === '' || $secret === '') {
            return PaymentInitiationResult::succeeded(
                transactionId: $transactionId,
                paymentUrl: $intent->sandboxUrlFor($transactionId),
                sandbox: true,
            );
        }

        // Click amount is in UZS (our totals are integer UZS).
        $amount = number_format($intent->amount, 2, '.', '');
        $paymentUrl = 'https://my.click.uz/services/pay?'
            .http_build_query([
                'service_id' => $serviceId,
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'transaction_param' => $intent->reference,
                'merchant_user_id' => (string) config('payment.click.merchant_user_id', ''),
                'return_url' => (string) config('payment.click.return_url', url('/')),
            ]);

        return PaymentInitiationResult::succeeded(
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
        );
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        // Click uses form fields + MD5 sign_string, verified in ClickWebhookController.
        return $signatureHeader !== null && $signatureHeader !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyClickSign(array $payload): bool
    {
        $secret = (string) config('payment.click.secret', '');
        if ($secret === '') {
            return false;
        }

        $clickTransId = (string) ($payload['click_trans_id'] ?? '');
        $serviceId = (string) ($payload['service_id'] ?? '');
        $merchantTransId = (string) ($payload['merchant_trans_id'] ?? '');
        $merchantPrepareId = (string) ($payload['merchant_prepare_id'] ?? '');
        $amount = (string) ($payload['amount'] ?? '');
        $action = (string) ($payload['action'] ?? '');
        $signTime = (string) ($payload['sign_time'] ?? '');
        $signString = (string) ($payload['sign_string'] ?? '');

        if ((int) $action === 0) {
            $digest = md5($clickTransId.$serviceId.$secret.$merchantTransId.$amount.$action.$signTime);
        } else {
            $digest = md5($clickTransId.$serviceId.$secret.$merchantTransId.$merchantPrepareId.$amount.$action.$signTime);
        }

        return hash_equals($digest, $signString);
    }

    public function pendingTransactionId(string $reference): string
    {
        return 'click-pending-'.$reference;
    }

    public function newClickTransRef(): string
    {
        return 'click-'.Str::uuid()->toString();
    }
}
