<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Models\VideoProcessingStep;
use App\Services\Video\VideoProcessingPipelineOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryVideoProcessingStepJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly string $videoId,
        public readonly VideoProcessingStepName $step,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(VideoProcessingPipelineOrchestrator $pipeline): void
    {
        $downstream = $this->downstreamSteps($this->step);

        VideoProcessingStep::query()
            ->where('video_id', $this->videoId)
            ->whereIn('step', array_map(static fn (VideoProcessingStepName $name): string => $name->value, $downstream))
            ->update([
                'status' => VideoProcessingStepStatus::Pending,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

        VideoProcessingStep::query()
            ->where('video_id', $this->videoId)
            ->where('step', $this->step->value)
            ->update([
                'status' => VideoProcessingStepStatus::Retrying,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

        $pipeline->run($this->videoId);
    }

    /**
     * @return list<VideoProcessingStepName>
     */
    private function downstreamSteps(VideoProcessingStepName $from): array
    {
        $names = VideoProcessingPipelineOrchestrator::allStepNames();
        $index = array_search($from, $names, true);

        if ($index === false) {
            return [$from];
        }

        return array_slice($names, $index);
    }
}
