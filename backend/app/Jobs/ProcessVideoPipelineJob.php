<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Video\VideoProcessingPipelineOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVideoPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** FFmpeg HLS (720p+480p) can take several minutes. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $videoId) {}

    public function handle(VideoProcessingPipelineOrchestrator $pipeline): void
    {
        try {
            $pipeline->run($this->videoId);
        } catch (\App\Exceptions\Domain\VideoProcessingException $exception) {
            if (in_array($exception->failureCode, ['duration_exceeded', 'validation_failed', 'unsupported_codec'], true)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $video = \App\Models\Video::find($this->videoId);
        if ($video?->status === \App\Enums\VideoStatus::Processing) {
            app(\App\Services\Video\VideoStateMachine::class)->markFailed(
                $video, $video->failure_code ?? 'transcode_error', $exception?->getMessage(),
            );
        }
    }
}
