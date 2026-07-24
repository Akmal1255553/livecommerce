<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WalletTransaction;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletTransaction */
class WalletTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'direction' => $this->amount >= 0 ? 'credit' : 'debit',
            'amount' => (new Money(abs($this->amount), $this->currency))->toArray(),
            'balance_after' => $this->balance_after !== null
                ? (new Money($this->balance_after, $this->currency))->toArray()
                : null,
            'method' => $this->method,
            'reference' => $this->reference,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
