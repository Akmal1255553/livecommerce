<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class CreateOrderData
{
    /**
     * @param  list<OrderLineSnapshot>  $lines
     */
    public function __construct(
        public string $userId,
        public string $storeId,
        public array $lines,
        public OrderTotals $totals,
        public PaymentSnapshot $payment,
        public ShipmentSnapshot $shipment,
        public ?string $notes = null,
    ) {}
}
