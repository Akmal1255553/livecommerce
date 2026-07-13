<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveAnalyticsEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property LiveAnalyticsEventType|string $event_type
 */
class LiveAnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $table = 'live_analytics_events';

    protected $fillable = [
        'live_session_id',
        'user_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => LiveAnalyticsEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
