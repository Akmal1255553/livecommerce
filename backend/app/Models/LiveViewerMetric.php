<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveViewerMetric extends Model
{
    protected $table = 'live_viewer_metrics';

    protected $primaryKey = 'live_session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'live_session_id',
        'current_viewers',
        'peak_viewers',
        'unique_viewers',
    ];

    protected function casts(): array
    {
        return [
            'current_viewers' => 'integer',
            'peak_viewers' => 'integer',
            'unique_viewers' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }
}
