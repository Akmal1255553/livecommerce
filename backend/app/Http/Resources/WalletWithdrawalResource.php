<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WalletWithdrawal;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletWithdrawal */
class WalletWithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'amount' => (new Money($this->amount, $this->currency))->toArray(),
            'fee' => (new Money($this->fee, $this->currency))->toArray(),
            'total' => (new Money($this->amount + $this->fee, $this->currency))->toArray(),
            'method' => $this->method,
            'card_last4' => $this->card_last4,
            'card_holder' => $this->card_holder,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}
