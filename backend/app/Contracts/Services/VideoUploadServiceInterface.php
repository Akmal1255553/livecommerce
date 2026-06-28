<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Storage\PresignedUploadData;
use App\Models\User;
use App\Models\Video;

interface VideoUploadServiceInterface
{
    /**
     * @param  array{title?: string|null, description?: string|null, visibility?: string, mime_type: string, file_size: int}  $data
     * @return array{video: Video, upload: PresignedUploadData}
     */
    public function initiateUpload(User $user, array $data): array;

    public function confirmUpload(User $user, string $videoId, ?string $checksum = null): Video;

    public function getViewableVideo(string $videoId, ?User $viewer): Video;
}
