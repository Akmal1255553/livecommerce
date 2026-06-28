<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;

class PublishVideoStep extends AbstractProcessingStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Publish;
    }

    protected function execute(Video $video): void
    {
        // Sprint 3.1: do not publish — video stays processing until 3.2.
    }

    protected function resultStatus(): VideoProcessingStepStatus
    {
        return VideoProcessingStepStatus::Skipped;
    }
}
