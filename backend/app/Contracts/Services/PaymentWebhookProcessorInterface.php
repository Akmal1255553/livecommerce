<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Payment\PaymentWebhookData;

interface PaymentWebhookProcessorInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload, string $rawBody, ?string $signatureHeader): void;

    /** Apply an already-authenticated sandbox / internal event. */
    public function apply(PaymentWebhookData $data): void;
}
