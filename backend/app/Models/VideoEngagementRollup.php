<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $video_id
 * @property Carbon $bucket_hour
 * @property int $views
 * @property int $likes
 * @property int $comments
 * @property int $shares
 * @property int $saves
 * @property int $completions
 * @property int $skips
 * @property int $follow_after_watch
 * @property int $watch_seconds
 */
class VideoEngagementRollup extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'video_id',
        'bucket_hour',
        'views',
        'likes',
        'comments',
        'shares',
        'saves',
        'completions',
        'skips',
        'follow_after_watch',
        'watch_seconds',
    ];

    protected function casts(): array
    {
        return [
            'bucket_hour' => 'datetime',
            'views' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'saves' => 'integer',
            'completions' => 'integer',
            'skips' => 'integer',
            'follow_after_watch' => 'integer',
            'watch_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
