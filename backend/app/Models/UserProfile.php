<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $display_name
 * @property int $follower_count
 * @property int $following_count
 * @property int $video_count
 * @property int $order_count
 * @property array<string, mixed> $notification_settings
 */
class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'follower_count',
        'following_count',
        'video_count',
        'order_count',
        'notification_settings',
    ];

    protected function casts(): array
    {
        return [
            'notification_settings' => 'array',
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
