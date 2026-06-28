<?php

declare(strict_types=1);

namespace App\Services\Video\Processing;

use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Enums\VideoStatus;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use App\Contracts\VideoProcessing\VideoProcessingStepInterface;
use Throwable;

abstract class AbstractProcessingStep implements VideoProcessingStepInterface
{
    public function run(Video $video): void
    {
        if ($this->isCompleted($video)) {
            return;
        }

        $record = $this->startStep($video);

        try {
            $this->execute($video);
            $this->completeStep($record, $this->resultStatus());
        } catch (Throwable $exception) {
            $this->failStep($record, $exception->getMessage());
            $video->update([
                'status' => VideoStatus::Failed,
                'processing_completed_at' => now(),
            ]);

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

    private function startStep(Video $video): VideoProcessingStep
    {
        return VideoProcessingStep::query()->create([
            'video_id' => $video->id,
            'step' => $this->name(),
            'status' => VideoProcessingStepStatus::Running,
            'attempt' => 1,
            'started_at' => now(),
            'created_at' => now(),
        ]);
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
