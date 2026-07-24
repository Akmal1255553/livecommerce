<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'available' => $this->availableMoney()->toArray(),
            'held' => $this->heldMoney()->toArray(),
            'total' => $this->totalMoney()->toArray(),
            'limits' => [
                'top_up_min' => (int) config('wallet.top_up.min'),
                'top_up_max' => (int) config('wallet.top_up.max'),
                'withdrawal_min' => (int) config('wallet.withdrawal.min'),
                'withdrawal_max' => (int) config('wallet.withdrawal.max'),
                'withdrawal_fee_percent' => (float) config('wallet.withdrawal.fee_percent'),
            ],
        ];
    }
}
