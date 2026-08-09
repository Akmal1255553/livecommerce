<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

final readonly class PaymentInitiationResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $paymentUrl = null,
        public ?string $failureReason = null,
        /** True when the URL is our own sandbox rather than a provider checkout page. */
        public bool $sandbox = false,
        public ?string $cryptoAddress = null,
        public ?string $cryptoAmount = null,
        public ?string $cryptoCurrency = null,
        public ?float $exchangeRate = null,
        public ?string $expiresAt = null,
        public ?string $qrPayload = null,
    ) {}

    public static function succeeded(
        ?string $transactionId = null,
        ?string $paymentUrl = null,
        bool $sandbox = false,
        ?string $cryptoAddress = null,
        ?string $cryptoAmount = null,
        ?string $cryptoCurrency = null,
        ?float $exchangeRate = null,
        ?string $expiresAt = null,
        ?string $qrPayload = null,
    ): self {
        return new self(
            success: true,
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
            sandbox: $sandbox,
            cryptoAddress: $cryptoAddress,
            cryptoAmount: $cryptoAmount,
            cryptoCurrency: $cryptoCurrency,
            exchangeRate: $exchangeRate,
            expiresAt: $expiresAt,
            qrPayload: $qrPayload,
        );
    }

    public static function failed(string $reason): self
    {
        return new self(success: false, failureReason: $reason);
    }
}
