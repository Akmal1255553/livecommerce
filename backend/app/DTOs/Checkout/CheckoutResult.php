<?php

declare(strict_types=1);

namespace App\DTOs\Checkout;

use App\Models\Order;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public ?string $paymentUrl = null,
    ) {}
}
