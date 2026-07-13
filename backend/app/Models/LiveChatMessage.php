<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveChatMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property LiveChatMessageType $type
 */
class LiveChatMessage extends Model
{
    public $timestamps = false;

    protected $table = 'live_chat_messages';

    protected $fillable = [
        'live_session_id',
        'user_id',
        'type',
        'message',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LiveChatMessageType::class,
            'metadata' => 'array',
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
