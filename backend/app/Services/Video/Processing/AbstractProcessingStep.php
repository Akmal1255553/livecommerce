<?php

declare(strict_types=1);

namespace App\Services\Video\Processing;

use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\VideoProcessing\VideoProcessingStepInterface;
use App\Enums\VideoProcessingStepStatus;
use App\Events\VideoProcessingFailed;
use App\Exceptions\Domain\VideoProcessingException;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use Throwable;

abstract class AbstractProcessingStep implements VideoProcessingStepInterface
{
    public function __construct(
        protected readonly VideoStateMachineInterface $stateMachine,
    ) {}

    public function run(Video $video): void
    {
        if ($this->isCompleted($video)) {
            return;
        }

        $record = $this->acquireStep($video);

        try {
            $this->execute($video->fresh() ?? $video);
            $this->completeStep($record, $this->resultStatus());
        } catch (VideoProcessingException $exception) {
            $this->failStep($record, $exception->getMessage());
            $this->stateMachine->markFailed($video->fresh() ?? $video, $exception->failureCode, $exception->getMessage());
            event(new VideoProcessingFailed($video->id, $this->name()->value, $exception->failureCode));

            throw $exception;
        } catch (Throwable $exception) {
            $this->failStep($record, $exception->getMessage());
            $this->stateMachine->markFailed($video->fresh() ?? $video, 'transcode_error', $exception->getMessage());
            event(new VideoProcessingFailed($video->id, $this->name()->value, 'transcode_error'));

            throw $exception;
        }
    }

    abstract protected function execute(Video $video): void;

    protected function resultStatus(): VideoProcessingStepStatus
    {
        return VideoProcessingStepStatus::Completed;
    }

    private function isCompleted(Video $video): bool
    {
        return VideoProcessingStep::query()
            ->where('video_id', $video->id)
            ->where('step', $this->name())
            ->whereIn('status', [
                VideoProcessingStepStatus::Completed,
                VideoProcessingStepStatus::Skipped,
            ])
            ->exists();
    }

    private function acquireStep(Video $video): VideoProcessingStep
    {
        $record = VideoProcessingStep::query()
            ->where('video_id', $video->id)
            ->where('step', $this->name())
            ->whereIn('status', [
                VideoProcessingStepStatus::Pending,
                VideoProcessingStepStatus::Retrying,
            ])
            ->first();

        if (! $record instanceof VideoProcessingStep) {
            throw new VideoProcessingException(
                'validation_failed',
                'Processing step is not pending: '.$this->name()->value,
            );
        }

        $record->update([
            'status' => VideoProcessingStepStatus::Running,
            'started_at' => now(),
            'error_message' => null,
        ]);

        return $record->fresh() ?? $record;
    }

    private function completeStep(VideoProcessingStep $record, VideoProcessingStepStatus $status): void
    {
        $record->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    private function failStep(VideoProcessingStep $record, string $message): void
    {
        $record->update([
            'status' => VideoProcessingStepStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
