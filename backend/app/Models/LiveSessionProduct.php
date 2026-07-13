<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionProduct extends Model
{
    protected $table = 'live_session_products';

    protected $fillable = [
        'live_session_id',
        'product_id',
        'is_pinned',
        'pinned_at',
        'offset_seconds',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'offset_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
