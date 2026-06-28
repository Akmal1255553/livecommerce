<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EngagementEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'user_id',
        'video_id',
        'session_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EngagementEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
