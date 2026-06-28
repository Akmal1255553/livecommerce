<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;

class TranscodeVideoStep extends AbstractProcessingStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Transcode;
    }

    protected function execute(Video $video): void
    {
        // Stub — real HLS in Sprint 3.2.
    }

    protected function resultStatus(): VideoProcessingStepStatus
    {
        return VideoProcessingStepStatus::Skipped;
    }
}
