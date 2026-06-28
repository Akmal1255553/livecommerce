<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Video;

interface VideoStateMachineInterface
{
    public function markUploaded(Video $video): Video;

    public function markQueued(Video $video): Video;

    public function markProcessing(Video $video): Video;

    public function markPublished(Video $video): Video;

    public function markFailed(Video $video, string $failureCode, ?string $failureMessage = null): Video;

    public function markRejected(Video $video): Video;
}
