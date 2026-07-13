<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property LiveSessionStatus $status
 */
class LiveSession extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'live_sessions';

    protected $fillable = [
        'id',
        'seller_id',
        'store_id',
        'title',
        'channel_id',
        'stream_key',
        'status',
        'viewer_count',
        'replay_url',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'viewer_count' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<LiveSessionProduct, $this>
     */
    public function sessionProducts(): HasMany
    {
        return $this->hasMany(LiveSessionProduct::class);
    }

    /**
     * Currently pinned products (for API resources).
     *
     * @return HasMany<LiveSessionProduct, $this>
     */
    public function pinnedProducts(): HasMany
    {
        return $this->hasMany(LiveSessionProduct::class)
            ->where('is_pinned', true)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<LiveChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class);
    }

    /**
     * @return HasOne<LiveViewerMetric, $this>
     */
    public function viewerMetrics(): HasOne
    {
        return $this->hasOne(LiveViewerMetric::class);
    }

    public function isLive(): bool
    {
        return $this->status === LiveSessionStatus::Live;
    }

    public function isOwnedBy(User $user): bool
    {
        return (string) $this->seller_id === (string) $user->id;
    }
}
