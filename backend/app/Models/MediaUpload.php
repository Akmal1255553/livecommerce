<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property MediaUploadStatus $status
 * @property Carbon|null $processed_at
 */
class MediaUpload extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'file_name',
        'mime_type',
        'file_size',
        'storage_path',
        'checksum',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MediaUploadStatus::class,
            'file_size' => 'integer',
            'processed_at' => 'datetime',
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
