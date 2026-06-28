<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\Services\VideoUploadServiceInterface;
use App\DTOs\Storage\PresignedUploadData;
use App\Enums\MediaUploadStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Events\VideoCreated;
use App\Events\VideoUploadConfirmed;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class VideoUploadService extends BaseService implements VideoUploadServiceInterface
{
    public function __construct(
        private readonly VideoRepositoryInterface $videos,
        private readonly MediaUploadRepositoryInterface $uploads,
        private readonly MediaServiceInterface $media,
        private readonly StorageServiceInterface $storage,
        private readonly VideoStateMachineInterface $stateMachine,
    ) {}

    /**
     * @param  array{title?: string|null, description?: string|null, visibility?: string, mime_type: string, file_size: int}  $data
     * @return array{video: Video, upload: PresignedUploadData}
     */
    public function initiateUpload(User $user, array $data): array
    {
        $this->ensureUserCanUpload($user);
        $this->enforceUploadRateLimit($user);

        $extension = $this->media->extensionForMime($data['mime_type']);
        $visibility = VideoVisibility::from($data['visibility'] ?? VideoVisibility::Public->value);

        $video = $this->videos->create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => VideoStatus::Uploading,
            'visibility' => $visibility,
        ]);

        $fileName = 'raw.'.$extension;
        $presigned = $this->media->createVideoUploadSession(
            $user,
            $video->id,
            $data['mime_type'],
            $data['file_size'],
            $fileName,
        );

        $this->videos->update($video, [
            'raw_video_url' => $presigned->storagePath,
        ]);

        event(new VideoCreated($video->id, $user->id));

        return [
            'video' => $video->fresh(['user']),
            'upload' => $presigned,
        ];
    }

    public function confirmUpload(User $user, string $videoId, ?string $checksum = null): Video
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video) {
            throw new ResourceNotFoundException('Video not found.');
        }

        if ($video->user_id !== $user->id) {
            throw new ForbiddenException('You cannot confirm this upload.');
        }

        if ($video->status !== VideoStatus::Uploading) {
            throw new ConflictException('Video is not awaiting upload confirmation.');
        }

        $upload = $this->uploads->findForVideo($videoId);

        if ($upload === null) {
            throw new ResourceNotFoundException('Upload session not found.');
        }

        if (! $this->storage->exists($upload->storage_path)) {
            throw ValidationException::withMessages([
                'upload' => ['Uploaded object was not found in storage.'],
            ]);
        }

        $this->uploads->updateStatus($upload, MediaUploadStatus::Uploaded->value, $checksum);

        $video = $this->stateMachine->markUploaded($video);
        $video = $this->stateMachine->markQueued($video);

        event(new VideoUploadConfirmed($video->id, $user->id, $upload->id));

        return $video->fresh(['user']);
    }

    public function getViewableVideo(string $videoId, ?User $viewer): Video
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video) {
            throw new ResourceNotFoundException('Video not found.');
        }

        if ($video->status === VideoStatus::Published) {
            $video->loadMissing('user');

            return $video;
        }

        if ($viewer !== null && $video->user_id === $viewer->id) {
            $video->loadMissing('user');

            return $video;
        }

        throw new ResourceNotFoundException('Video not found.');
    }

    private function ensureUserCanUpload(User $user): void
    {
        if (! $user->isActive()) {
            throw new ForbiddenException('Your account cannot upload videos.');
        }
    }

    private function enforceUploadRateLimit(User $user): void
    {
        $key = 'video-upload:'.$user->id;
        $maxAttempts = (int) config('storage.video_upload_rate_limit', 10);
        $decay = (int) config('storage.video_upload_rate_decay_seconds', 3600);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new TooManyRequestsHttpException($decay, 'Upload rate limit exceeded.');
        }

        RateLimiter::hit($key, $decay);
    }
}
