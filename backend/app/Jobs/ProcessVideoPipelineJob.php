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

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $videoId) {}

    public function handle(VideoProcessingPipelineOrchestrator $pipeline): void
    {
        $pipeline->run($this->videoId);
    }
}
