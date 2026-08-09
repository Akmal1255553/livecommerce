<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymeTransactionState;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $payme_transaction_id
 * @property string $reference
 * @property int $amount
 * @property PaymeTransactionState $state
 * @property int|null $reason
 * @property int $payme_time
 * @property int $create_time
 * @property int|null $perform_time
 * @property int|null $cancel_time
 */
class PaymeTransaction extends Model
{
    protected $fillable = [
        'payme_transaction_id',
        'reference',
        'amount',
        'state',
        'reason',
        'payme_time',
        'create_time',
        'perform_time',
        'cancel_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PaymeTransactionState::class,
            'amount' => 'integer',
            'reason' => 'integer',
            'payme_time' => 'integer',
            'create_time' => 'integer',
            'perform_time' => 'integer',
            'cancel_time' => 'integer',
        ];
    }
}
