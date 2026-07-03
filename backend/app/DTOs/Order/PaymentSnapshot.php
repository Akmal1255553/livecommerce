<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\ValueObjects\Money;

final readonly class PaymentSnapshot
{
    public function __construct(
        public ?string $provider,
        public string $method,
        public ?string $transactionId,
        public Money $amount,
        public string $status,
        public ?string $reference = null,
        public ?\DateTimeInterface $paidAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOrderAttributes(): array
    {
        return [
            'payment_provider' => $this->provider,
            'payment_method' => $this->method,
            'payment_transaction_id' => $this->transactionId,
            'payment_status' => $this->status,
            'payment_reference' => $this->reference,
            'currency' => $this->amount->currency,
            'paid_at' => $this->paidAt,
        ];
    }
}
