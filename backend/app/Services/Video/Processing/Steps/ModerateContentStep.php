<?php

declare(strict_types=1);

namespace App\Services\Video\Processing\Steps;

use App\Enums\VideoProcessingStepName;
use App\Models\Video;
use App\Services\Video\Processing\AbstractProcessingStep;
use Illuminate\Support\Facades\Log;

class ModerateContentStep extends AbstractProcessingStep
{
    public function name(): VideoProcessingStepName
    {
        return VideoProcessingStepName::Moderation;
    }

    protected function execute(Video $video): void
    {
        Log::info('Moderation stub auto-approved.', ['video_id' => $video->id]);
    }
}
