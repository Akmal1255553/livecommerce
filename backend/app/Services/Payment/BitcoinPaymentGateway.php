<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bitcoin top-up / checkout via NOWPayments-style API.
 * Without BITCOIN_API_KEY, returns a sandbox address + fixed UZS/BTC rate.
 */
class BitcoinPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'bitcoin';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiationResult
    {
        $apiKey = (string) config('payment.bitcoin.api_key', '');
        $rate = $this->uzsPerBtc();

        if ($rate <= 0) {
            return PaymentInitiationResult::failed('Bitcoin exchange rate is unavailable.');
        }

        $btcAmount = number_format($intent->amount / $rate, 8, '.', '');
        $expiresAt = now()->addMinutes((int) config('payment.bitcoin.invoice_ttl_minutes', 30))->toIso8601String();

        if ($apiKey === '') {
            $address = (string) config('payment.bitcoin.sandbox_address', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');
            $qrPayload = 'bitcoin:'.$address.'?amount='.$btcAmount;

            return PaymentInitiationResult::succeeded(
                transactionId: 'btc-sandbox-'.Str::uuid()->toString(),
                paymentUrl: $intent->sandboxUrlFor('btc-sandbox'),
                sandbox: true,
                cryptoAddress: $address,
                cryptoAmount: $btcAmount,
                cryptoCurrency: 'BTC',
                exchangeRate: $rate,
                expiresAt: $expiresAt,
                qrPayload: $qrPayload,
            );
        }

        return $this->createNowPaymentsInvoice($intent, $btcAmount, $rate, $expiresAt, $apiKey);
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $secret = (string) config('payment.bitcoin.ipn_secret', '');
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawPayload, $secret);

        return hash_equals($expected, strtolower($signatureHeader))
            || hash_equals($expected, $signatureHeader);
    }

    /**
     * Quote UZS → BTC without creating an invoice.
     *
     * @return array{crypto_amount: string, crypto_currency: string, exchange_rate: float, amount: int, currency: string}
     */
    public function quote(int $amountUzs): array
    {
        $rate = $this->uzsPerBtc();
        $btcAmount = $rate > 0
            ? number_format($amountUzs / $rate, 8, '.', '')
            : '0';

        return [
            'amount' => $amountUzs,
            'currency' => 'UZS',
            'crypto_amount' => $btcAmount,
            'crypto_currency' => 'BTC',
            'exchange_rate' => $rate,
        ];
    }

    private function uzsPerBtc(): float
    {
        $configured = (float) config('payment.bitcoin.sandbox_rate_uzs_per_btc', 1_200_000_000);

        $apiKey = (string) config('payment.bitcoin.api_key', '');
        if ($apiKey === '') {
            return $configured;
        }

        try {
            $response = Http::timeout(5)
                ->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'bitcoin',
                    'vs_currencies' => 'uzs',
                ]);

            if ($response->successful()) {
                $rate = (float) data_get($response->json(), 'bitcoin.uzs', 0);
                if ($rate > 0) {
                    return $rate;
                }
            }
        } catch (\Throwable) {
            // Fall through to configured sandbox rate.
        }

        return $configured;
    }

    private function createNowPaymentsInvoice(
        PaymentIntent $intent,
        string $btcAmount,
        float $rate,
        string $expiresAt,
        string $apiKey,
    ): PaymentInitiationResult {
        $baseUrl = rtrim((string) config('payment.bitcoin.api_url', 'https://api.nowpayments.io/v1'), '/');
        $ipnCallback = (string) config('payment.bitcoin.ipn_callback_url', url('/api/v1/webhooks/bitcoin'));

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl.'/payment', [
                    'price_amount' => $intent->amount,
                    'price_currency' => strtolower($intent->currency),
                    'pay_currency' => 'btc',
                    'order_id' => $intent->reference,
                    'order_description' => $intent->description,
                    'ipn_callback_url' => $ipnCallback,
                ]);
        } catch (\Throwable $e) {
            return PaymentInitiationResult::failed('Bitcoin provider unreachable: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return PaymentInitiationResult::failed(
                'Bitcoin provider rejected the invoice (HTTP '.$response->status().').',
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $address = (string) ($body['pay_address'] ?? '');
        $payAmount = (string) ($body['pay_amount'] ?? $btcAmount);
        $paymentId = (string) ($body['payment_id'] ?? ('btc-'.Str::uuid()->toString()));
        $invoiceUrl = isset($body['invoice_url']) ? (string) $body['invoice_url'] : null;

        if ($address === '') {
            return PaymentInitiationResult::failed('Bitcoin provider did not return a pay address.');
        }

        $qrPayload = 'bitcoin:'.$address.'?amount='.$payAmount;

        return PaymentInitiationResult::succeeded(
            transactionId: $paymentId,
            paymentUrl: $invoiceUrl,
            sandbox: false,
            cryptoAddress: $address,
            cryptoAmount: $payAmount,
            cryptoCurrency: 'BTC',
            exchangeRate: $rate,
            expiresAt: $expiresAt,
            qrPayload: $qrPayload,
        );
    }
}
