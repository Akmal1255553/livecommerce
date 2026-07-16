<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $rawBody,
        public ?string $signature,
    ) {}

    public function handle(PaymentWebhookProcessorInterface $processor): void
    {
        try {
            $processor->process($this->payload, $this->rawBody, $this->signature);
        } catch (\Throwable $e) {
            Log::warning('payment.webhook.failed', [
                'message' => $e->getMessage(),
                'transaction_id' => $this->payload['transaction_id'] ?? null,
                'order_id' => $this->payload['order_id'] ?? null,
            ]);

            throw $e;
        }
    }
}
