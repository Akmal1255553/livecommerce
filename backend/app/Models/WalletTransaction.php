<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $wallet_id
 * @property WalletTransactionType $type
 * @property WalletTransactionStatus $status
 * @property int $amount
 * @property int|null $balance_after
 * @property string $currency
 * @property string|null $method
 * @property string|null $reference
 * @property string|null $related_id
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $completed_at
 */
class WalletTransaction extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'wallet_id',
        'type',
        'status',
        'amount',
        'balance_after',
        'currency',
        'method',
        'reference',
        'related_id',
        'description',
        'metadata',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'status' => WalletTransactionStatus::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
