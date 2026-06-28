<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Enums\MediaUploadStatus;
use App\Models\MediaUpload;

interface MediaUploadRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MediaUpload;

    public function updateStatus(MediaUpload $upload, MediaUploadStatus|string $status, ?string $checksum = null): MediaUpload;

    public function findForVideo(string $videoId): ?MediaUpload;
}
