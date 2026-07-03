<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency ?? 'UZS';

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'version' => $this->version,
            'store' => $this->whenLoaded('store', fn () => new StoreCompactResource($this->store)),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'subtotal' => Money::uzs((int) $this->subtotal)->toArray(),
                'shipping' => Money::uzs((int) $this->shipping_cost)->toArray(),
                'discount' => Money::uzs((int) $this->discount)->toArray(),
                'tax' => Money::uzs((int) $this->tax)->toArray(),
                'total' => Money::uzs((int) $this->total)->toArray(),
                'currency' => $currency,
            ],
            'payment' => [
                'provider' => $this->payment_provider,
                'method' => $this->payment_method,
                'transaction_id' => $this->payment_transaction_id,
                'currency' => $currency,
                'amount' => Money::uzs((int) $this->total)->toArray(),
                'status' => $this->payment_status->value,
                'reference' => $this->payment_reference,
                'paid_at' => $this->paid_at?->toIso8601String(),
            ],
            'shipment' => [
                'address' => $this->shipping_address,
                'carrier' => $this->carrier,
                'tracking_number' => $this->tracking_number,
                'estimated_delivery' => $this->estimated_delivery_at?->toIso8601String(),
                'shipped_at' => $this->shipped_at?->toIso8601String(),
                'actual_delivery' => $this->delivered_at?->toIso8601String(),
            ],
            'timeline' => $this->whenLoaded('transitions', function () {
                return $this->transitions->map(
                    fn (OrderStatusTransition $transition) => [
                        'from_status' => $transition->from_status->value,
                        'to_status' => $transition->to_status->value,
                        'actor_type' => $transition->actor_type->value,
                        'reason' => $transition->reason,
                        'created_at' => $transition->created_at?->toIso8601String(),
                    ],
                )->all();
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
