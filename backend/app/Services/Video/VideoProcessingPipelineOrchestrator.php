<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\VideoProcessing\VideoProcessingStepInterface;
use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Enums\VideoStatus;
use App\Events\VideoProcessingStarted;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use App\Services\Video\Processing\Steps\ExtractMetadataStep;
use App\Services\Video\Processing\Steps\GenerateThumbnailStep;
use App\Services\Video\Processing\Steps\ModerateContentStep;
use App\Services\Video\Processing\Steps\PublishVideoStep;
use App\Services\Video\Processing\Steps\TranscodeHls480Step;
use App\Services\Video\Processing\Steps\TranscodeHls720Step;
use App\Services\Video\Processing\Steps\ValidateVideoStep;
use App\Services\Video\Processing\Steps\VirusScanStep;

class VideoProcessingPipelineOrchestrator
{
    /**
     * @var list<class-string<VideoProcessingStepInterface>>
     */
    private const ALL_STEPS = [
        VirusScanStep::class,
        ExtractMetadataStep::class,
        ValidateVideoStep::class,
        GenerateThumbnailStep::class,
        TranscodeHls720Step::class,
        TranscodeHls480Step::class,
        ModerateContentStep::class,
        PublishVideoStep::class,
    ];

    /**
     * @var list<list<class-string<VideoProcessingStepInterface>>>
     */
    private const STAGES = [
        [VirusScanStep::class, ExtractMetadataStep::class],
        [ValidateVideoStep::class],
        [GenerateThumbnailStep::class],
        [TranscodeHls720Step::class, TranscodeHls480Step::class],
        [ModerateContentStep::class],
        [PublishVideoStep::class],
    ];

    public function __construct(
        private readonly VideoStateMachine $stateMachine,
    ) {}

    public function run(string $videoId): void
    {
        $video = Video::query()->findOrFail($videoId);

        // Permanent validation failures must never re-enter processing on a
        // queue retry (including jobs enqueued before this fix was deployed).
        if (in_array($video->failure_code, ['duration_exceeded', 'validation_failed', 'unsupported_codec'], true)
            && in_array($video->status, [VideoStatus::Failed, VideoStatus::Processing], true)) {
            $this->stateMachine->markFailed($video, $video->failure_code, $video->failure_message);

            return;
        }

        if ($video->status === VideoStatus::Failed) {
            VideoProcessingStep::query()->where('video_id', $videoId)
                ->where('status', VideoProcessingStepStatus::Failed)
                ->update(['status' => VideoProcessingStepStatus::Retrying->value]);
        }

        if ($video->status === VideoStatus::Published) {
            return;
        }

        if (in_array($video->status, [VideoStatus::Queued, VideoStatus::Failed], true)) {
            $this->stateMachine->markProcessing($video->fresh() ?? $video);
        } elseif ($video->status !== VideoStatus::Processing) {
            throw new \InvalidArgumentException(
                'Cannot start pipeline from status: '.$video->status->value
            );
        }

        event(new VideoProcessingStarted($videoId));

        $this->seedPendingSteps($videoId);

        foreach (self::STAGES as $stage) {
            $this->runStage($videoId, $stage);
        }
    }

    /**
     * @param  list<class-string<VideoProcessingStepInterface>>  $steps
     */
    private function runStage(string $videoId, array $steps): void
    {
        foreach ($steps as $stepClass) {
            /** @var VideoProcessingStepInterface $step */
            $step = app($stepClass);
            $step->run(Video::query()->findOrFail($videoId));
        }
    }

    private function seedPendingSteps(string $videoId): void
    {
        $rows = [];
        foreach (self::ALL_STEPS as $stepClass) {
            /** @var VideoProcessingStepInterface $step */
            $step = app($stepClass);

            $rows[] = ['video_id' => $videoId, 'step' => $step->name()->value,
                'status' => VideoProcessingStepStatus::Pending->value, 'attempt' => 1, 'created_at' => now()];
        }
        VideoProcessingStep::query()->insertOrIgnore($rows);
    }

    /**
     * @return list<VideoProcessingStepName>
     */
    public static function allStepNames(): array
    {
        return array_map(
            static fn (string $class): VideoProcessingStepName => app($class)->name(),
            self::ALL_STEPS,
        );
    }
}
