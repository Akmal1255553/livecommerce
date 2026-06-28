<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaAssetType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property MediaAssetType $type
 * @property array<string, mixed> $metadata
 * @property Carbon $created_at
 */
class MediaAsset extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'video_id',
        'type',
        'storage_path',
        'mime_type',
        'byte_size',
        'width',
        'height',
        'checksum',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaAssetType::class,
            'metadata' => 'array',
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'datetime',
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
