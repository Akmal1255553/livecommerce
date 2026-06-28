<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'follows';

    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
