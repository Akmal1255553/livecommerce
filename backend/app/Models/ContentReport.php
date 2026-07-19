<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentReportStatus;
use App\Enums\ContentReportTarget;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $reporter_id
 * @property ContentReportTarget $target_type
 * @property string $target_id
 * @property string $reason
 * @property ContentReportStatus $status
 * @property string|null $resolved_by
 * @property string|null $resolution_note
 */
class ContentReport extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reporter_id',
        'target_type',
        'target_id',
        'reason',
        'status',
        'resolved_by',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => ContentReportTarget::class,
            'status' => ContentReportStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
