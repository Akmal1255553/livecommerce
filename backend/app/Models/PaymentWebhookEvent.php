<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $idempotency_key
 * @property string $event
 * @property string $transaction_id
 * @property string|null $order_id
 * @property array<string, mixed> $payload
 * @property Carbon $processed_at
 */
class PaymentWebhookEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'idempotency_key',
        'event',
        'transaction_id',
        'order_id',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
