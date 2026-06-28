<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Enums\VideoVisibility;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;

class VideoManagementService extends BaseService
{
    public function __construct(
        private readonly VideoRepositoryInterface $videos,
    ) {}

    /**
     * @param  array{title?: string|null, description?: string|null, visibility?: string}  $data
     */
    public function updateMetadata(User $user, string $videoId, array $data): Video
    {
        $video = $this->findOwnedVideo($user, $videoId);

        $attributes = [];

        if (array_key_exists('title', $data)) {
            $attributes['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'];
        }

        if (array_key_exists('visibility', $data)) {
            $attributes['visibility'] = VideoVisibility::from($data['visibility']);
        }

        return $this->videos->update($video, $attributes)->fresh(['user']);
    }

    public function softDelete(User $user, string $videoId): void
    {
        $video = $this->findOwnedVideo($user, $videoId);
        $video->delete();
    }

    private function findOwnedVideo(User $user, string $videoId): Video
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video) {
            throw new ResourceNotFoundException('Video not found.');
        }

        if ($video->user_id !== $user->id) {
            throw new ForbiddenException('You cannot modify this video.');
        }

        return $video;
    }
}
