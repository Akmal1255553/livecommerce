<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShareChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoShare extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'video_id',
        'channel',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ShareChannel::class,
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
