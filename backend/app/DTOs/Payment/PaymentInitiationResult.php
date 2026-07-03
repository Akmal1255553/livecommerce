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
    ) {}

    public static function succeeded(?string $transactionId = null, ?string $paymentUrl = null): self
    {
        return new self(success: true, transactionId: $transactionId, paymentUrl: $paymentUrl);
    }

    public static function failed(string $reason): self
    {
        return new self(success: false, failureReason: $reason);
    }
}
