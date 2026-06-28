<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property VideoProcessingStepName $step
 * @property VideoProcessingStepStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
class VideoProcessingStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'video_id',
        'step',
        'status',
        'attempt',
        'error_message',
        'started_at',
        'completed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => VideoProcessingStepName::class,
            'status' => VideoProcessingStepStatus::class,
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
