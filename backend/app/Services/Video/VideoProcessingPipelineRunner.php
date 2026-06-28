<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\VideoProcessing\VideoProcessingStepInterface;
use App\Models\Video;
use App\Services\Video\Processing\Steps\ExtractMetadataStep;
use App\Services\Video\Processing\Steps\GenerateThumbnailStep;
use App\Services\Video\Processing\Steps\ModerateContentStep;
use App\Services\Video\Processing\Steps\PublishVideoStep;
use App\Services\Video\Processing\Steps\TranscodeVideoStep;
use App\Services\Video\Processing\Steps\VirusScanStep;

class VideoProcessingPipelineRunner
{
    /**
     * @var list<class-string<VideoProcessingStepInterface>>
     */
    private array $steps = [
        VirusScanStep::class,
        ExtractMetadataStep::class,
        GenerateThumbnailStep::class,
        TranscodeVideoStep::class,
        ModerateContentStep::class,
        PublishVideoStep::class,
    ];

    public function run(string $videoId): void
    {
        $video = Video::query()->findOrFail($videoId);

        foreach ($this->steps as $stepClass) {
            /** @var VideoProcessingStepInterface $step */
            $step = app($stepClass);
            $step->run($video->fresh() ?? $video);
        }
    }
}
