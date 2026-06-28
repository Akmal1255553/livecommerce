<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Enums\VideoProcessingStepName;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;

class ExtractMetadataStep extends AbstractProcessingStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Metadata;
    }

    protected function execute(Video $video): void
    {
        $video->update([
            'duration' => 0,
            'width' => null,
            'height' => null,
        ]);
    }
}
