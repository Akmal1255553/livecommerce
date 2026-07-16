<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Payment\PaymentInitiationResult;
use App\Models\Order;

interface PaymentGatewayInterface
{
    /** Provider label stored on the order (fake, local, click, …). */
    public function name(): string;

    public function initiate(Order $order): PaymentInitiationResult;

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool;
}
