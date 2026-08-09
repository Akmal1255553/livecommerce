<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bitcoin top-up / checkout via NOWPayments-style API.
 * Without BITCOIN_API_KEY, returns a sandbox address + fixed UZS/BTC rate.
 *
 * NOWPayments fiat list does not include UZS — invoices are priced in USD
 * (converted from UZS). Wallet/order amounts stay in UZS; webhooks resolve
 * the charged amount from our reference, not from IPN fiat fields.
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

        $rates = $this->coingeckoBtcRates();
        if ($rates['uzs'] > 0) {
            return $rates['uzs'];
        }

        if ($rates['usd'] > 0) {
            $uzsPerUsd = $this->uzsPerUsd($rates['usd']);
            if ($uzsPerUsd > 0) {
                return $rates['usd'] * $uzsPerUsd;
            }
        }

        return $configured;
    }

    /**
     * Convert UZS (integer soms) to a USD amount NOWPayments will accept.
     */
    private function amountInUsd(int $amountUzs): float
    {
        $uzsPerUsd = $this->uzsPerUsd();
        if ($uzsPerUsd <= 0) {
            return 0.0;
        }

        return round($amountUzs / $uzsPerUsd, 2);
    }

    private function uzsPerUsd(?float $btcUsd = null): float
    {
        $configured = (float) config('payment.bitcoin.uzs_per_usd', 12_500);
        $rates = $this->coingeckoBtcRates();

        if ($rates['uzs'] > 0 && $rates['usd'] > 0) {
            return $rates['uzs'] / $rates['usd'];
        }

        if ($btcUsd !== null && $btcUsd > 0 && $rates['uzs'] > 0) {
            return $rates['uzs'] / $btcUsd;
        }

        return $configured > 0 ? $configured : 12_500.0;
    }

    /**
     * @return array{usd: float, uzs: float}
     */
    private function coingeckoBtcRates(): array
    {
        try {
            $response = Http::timeout(5)
                ->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'bitcoin',
                    'vs_currencies' => 'usd,uzs',
                ]);

            if ($response->successful()) {
                return [
                    'usd' => (float) data_get($response->json(), 'bitcoin.usd', 0),
                    'uzs' => (float) data_get($response->json(), 'bitcoin.uzs', 0),
                ];
            }
        } catch (\Throwable) {
            // Fall through.
        }

        return ['usd' => 0.0, 'uzs' => 0.0];
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

        $fiatCurrency = strtolower($intent->currency);
        $priceAmount = $intent->amount;

        // NOWPayments does not accept UZS — invoice in USD, settle amount from our reference on IPN.
        if ($fiatCurrency === 'uzs') {
            $usd = $this->amountInUsd($intent->amount);
            if ($usd < 0.01) {
                return PaymentInitiationResult::failed('Top-up amount is below the Bitcoin minimum.');
            }
            $priceAmount = $usd;
            $fiatCurrency = 'usd';
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl.'/payment', [
                    'price_amount' => $priceAmount,
                    'price_currency' => $fiatCurrency,
                    'pay_currency' => 'btc',
                    'order_id' => $intent->reference,
                    'order_description' => $intent->description,
                    'ipn_callback_url' => $ipnCallback,
                ]);
        } catch (\Throwable $e) {
            return PaymentInitiationResult::failed('Bitcoin provider unreachable: '.$e->getMessage());
        }

        if (! $response->successful()) {
            $providerMessage = (string) (
                data_get($response->json(), 'message')
                ?? data_get($response->json(), 'error')
                ?? Str::limit($response->body(), 200)
            );

            Log::warning('payment.bitcoin.invoice.rejected', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'price_amount' => $priceAmount,
                'price_currency' => $fiatCurrency,
            ]);

            return PaymentInitiationResult::failed(
                'Bitcoin provider rejected the invoice (HTTP '.$response->status().')'
                .($providerMessage !== '' ? ': '.$providerMessage : '.'),
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
