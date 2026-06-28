<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VideoUploadConfirmed;
use App\Jobs\ProcessVideoPipelineJob;

class DispatchVideoProcessingPipeline
{
    public function handle(VideoUploadConfirmed $event): void
    {
        $dispatch = ProcessVideoPipelineJob::dispatch($event->videoId)
            ->onQueue('video-processing');

        if (config('queue.default') !== 'sync') {
            $dispatch->afterResponse();
        }
    }
}
