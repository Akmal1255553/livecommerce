<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

final readonly class PaymentWebhookData
{
    public function __construct(
        public string $event,
        public string $transactionId,
        public string $orderId,
        public int $amount,
        public string $currency,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $amount = $payload['amount'] ?? 0;
        if (is_float($amount) || is_string($amount)) {
            $amount = (int) round((float) $amount);
        }

        return new self(
            event: (string) ($payload['event'] ?? ''),
            transactionId: (string) ($payload['transaction_id'] ?? ''),
            orderId: (string) ($payload['order_id'] ?? ''),
            amount: (int) $amount,
            currency: (string) ($payload['currency'] ?? 'UZS'),
        );
    }

    public function idempotencyKey(): string
    {
        return $this->event.':'.$this->transactionId;
    }
}
