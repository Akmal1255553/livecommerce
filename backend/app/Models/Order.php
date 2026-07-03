<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int $version
 * @property array<string, mixed> $shipping_address
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $estimated_delivery_at
 * @property Carbon|null $created_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusTransition> $transitions
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_number',
        'user_id',
        'store_id',
        'status',
        'version',
        'status_before_refund',
        'active_refund_id',
        'subtotal',
        'shipping_cost',
        'discount',
        'tax',
        'total',
        'currency',
        'shipping_address',
        'payment_method',
        'payment_provider',
        'payment_status',
        'payment_transaction_id',
        'payment_reference',
        'carrier',
        'tracking_number',
        'estimated_delivery_at',
        'notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'shipping_address' => 'array',
            'version' => 'integer',
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderStatusTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(OrderStatusTransition::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<RefundRequest, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    /**
     * @return BelongsTo<RefundRequest, $this>
     */
    public function activeRefund(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'active_refund_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if ($order->isDirty('order_number')) {
                throw new \LogicException('Order number cannot be changed.');
            }
        });
    }
}
