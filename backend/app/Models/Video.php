<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property VideoStatus $status
 * @property VideoVisibility $visibility
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $processing_completed_at
 * @property Carbon|null $published_at
 */
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'raw_video_url',
        'duration',
        'width',
        'height',
        'view_count',
        'like_count',
        'comment_count',
        'share_count',
        'status',
        'visibility',
        'codec',
        'bitrate',
        'failure_code',
        'failure_message',
        'processing_started_at',
        'processing_completed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'visibility' => VideoVisibility::class,
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'published_at' => 'datetime',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'share_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
