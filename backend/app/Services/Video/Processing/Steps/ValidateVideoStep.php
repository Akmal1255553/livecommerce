<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Enums\VideoProcessingStepName;
use App\Exceptions\Domain\VideoProcessingException;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;

class ValidateVideoStep extends AbstractProcessingStep
{
    /**
     * @var list<string>
     */
    private const ALLOWED_CODECS = ['h264', 'hevc', 'vp9', 'av1'];

    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Validate;
    }

    protected function execute(Video $video): void
    {
        $maxDuration = (int) config('video.max_duration_seconds', 60);

        if ($video->duration === null || $video->duration <= 0) {
            throw new VideoProcessingException('validation_failed', 'Video duration is missing or invalid.');
        }

        if ($video->duration > $maxDuration) {
            throw new VideoProcessingException('duration_exceeded', 'Video exceeds maximum duration of '.$maxDuration.' seconds.');
        }

        if ($video->width === null || $video->height === null || $video->width <= 0 || $video->height <= 0) {
            throw new VideoProcessingException('validation_failed', 'Video dimensions are missing or invalid.');
        }

        if ($video->codec !== null && ! in_array($video->codec, self::ALLOWED_CODECS, true)) {
            throw new VideoProcessingException('unsupported_codec', 'Unsupported video codec: '.$video->codec);
        }
    }
}
