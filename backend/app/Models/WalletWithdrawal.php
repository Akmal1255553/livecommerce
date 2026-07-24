<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $wallet_id
 * @property string|null $transaction_id
 * @property int $amount
 * @property int $fee
 * @property string $currency
 * @property string $method
 * @property string $card_last4
 * @property string $card_holder
 * @property WithdrawalStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $processed_at
 */
class WalletWithdrawal extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'amount',
        'fee',
        'currency',
        'method',
        'card_last4',
        'card_holder',
        'status',
        'rejection_reason',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id');
    }
}
