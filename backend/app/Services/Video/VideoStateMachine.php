<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Enums\VideoStatus;
use App\Models\Video;
use App\Services\BaseService;
use InvalidArgumentException;

class VideoStateMachine extends BaseService implements VideoStateMachineInterface
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'uploading' => ['uploaded'],
        'uploaded' => ['queued'],
        'queued' => ['processing'],
        'processing' => ['published', 'failed', 'rejected'],
        'failed' => ['processing'],
    ];

    public function __construct(
        private readonly VideoRepositoryInterface $videos,
    ) {}

    public function markUploaded(Video $video): Video
    {
        return $this->transition($video, VideoStatus::Uploaded);
    }

    public function markQueued(Video $video): Video
    {
        return $this->transition($video, VideoStatus::Queued);
    }

    public function markProcessing(Video $video): Video
    {
        return $this->transition($video, VideoStatus::Processing, [
            'processing_started_at' => now(),
        ]);
    }

    public function markPublished(Video $video): Video
    {
        return $this->transition($video, VideoStatus::Published, [
            'processing_completed_at' => now(),
            'published_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);
    }

    public function markFailed(Video $video, string $failureCode, ?string $failureMessage = null): Video
    {
        return $this->transition($video, VideoStatus::Failed, [
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'processing_completed_at' => now(),
        ]);
    }

    public function markRejected(Video $video): Video
    {
        return $this->transition($video, VideoStatus::Rejected, [
            'processing_completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Video $video, VideoStatus $to, array $attributes = []): Video
    {
        $from = $video->status;

        if ($from === $to) {
            return $this->videos->update($video, $attributes);
        }

        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidArgumentException(
                "Invalid video status transition: {$from->value} → {$to->value}"
            );
        }

        return $this->videos->update($video, array_merge(['status' => $to], $attributes));
    }
}
