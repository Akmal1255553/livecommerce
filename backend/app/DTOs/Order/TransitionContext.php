<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\Enums\OrderActor;
use App\Enums\OrderStatus;

final readonly class TransitionContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public OrderActor $actor,
        public ?string $actorId = null,
        public ?string $idempotencyKey = null,
        public ?string $reason = null,
        public ?int $expectedVersion = null,
        public array $metadata = [],
        public ?ShipmentSnapshot $shipmentPatch = null,
        public ?PaymentSnapshot $paymentPatch = null,
    ) {}

    public function targetStatus(): ?OrderStatus
    {
        return $this->metadata['target_status'] ?? null;
    }
}
