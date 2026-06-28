<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'status' => MediaUploadStatus::class,
            'file_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
