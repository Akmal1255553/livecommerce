<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property InventoryReservationStatus $status
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
class InventoryReservation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reservation_group_id',
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'status',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryReservationStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
