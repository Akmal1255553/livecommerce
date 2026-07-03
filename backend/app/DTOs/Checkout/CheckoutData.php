<?php

declare(strict_types=1);

namespace App\DTOs\Checkout;

final readonly class CheckoutData
{
    /**
     * @param  array<string, mixed>  $shippingAddress
     */
    public function __construct(
        public string $userId,
        public int $cartVersion,
        public string $idempotencyKey,
        public array $shippingAddress,
        public string $paymentMethod,
        public ?string $couponCode = null,
        public ?string $notes = null,
    ) {}
}
