<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;

/**
 * Picks a gateway by checkout / top-up method without replacing the configured
 * default driver used by webhooks that still bind PaymentGatewayInterface.
 */
class PaymentGatewayResolver
{
    public function __construct(
        private readonly PaymentGatewayInterface $default,
        private readonly BitcoinPaymentGateway $bitcoin,
        private readonly CardPaymentGateway $card,
    ) {}

    public function resolve(?string $method): PaymentGatewayInterface
    {
        return match (strtolower((string) $method)) {
            'bitcoin' => $this->bitcoin,
            'card' => $this->card,
            default => $this->default,
        };
    }
}
