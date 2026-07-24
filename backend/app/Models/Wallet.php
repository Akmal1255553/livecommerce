<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $user_id
 * @property int $available_balance
 * @property int $held_balance
 * @property string $currency
 */
class Wallet extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'available_balance',
        'held_balance',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_balance' => 'integer',
            'held_balance' => 'integer',
        ];
    }

    public function availableMoney(): Money
    {
        return new Money($this->available_balance, $this->currency);
    }

    public function heldMoney(): Money
    {
        return new Money($this->held_balance, $this->currency);
    }

    public function totalMoney(): Money
    {
        return new Money($this->available_balance + $this->held_balance, $this->currency);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * @return HasMany<WalletWithdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(WalletWithdrawal::class);
    }
}
