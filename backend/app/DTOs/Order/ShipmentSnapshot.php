<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class ShipmentSnapshot
{
    /**
     * @param  array<string, mixed>  $address
     */
    public function __construct(
        public array $address,
        public ?string $carrier = null,
        public ?string $trackingNumber = null,
        public ?\DateTimeInterface $estimatedDelivery = null,
        public ?\DateTimeInterface $shippedAt = null,
        public ?\DateTimeInterface $actualDelivery = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOrderAttributes(): array
    {
        return [
            'shipping_address' => $this->address,
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'estimated_delivery_at' => $this->estimatedDelivery,
            'shipped_at' => $this->shippedAt,
            'delivered_at' => $this->actualDelivery,
        ];
    }
}
