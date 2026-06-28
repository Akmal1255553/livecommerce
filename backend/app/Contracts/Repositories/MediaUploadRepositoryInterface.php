<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\MediaUpload;

interface MediaUploadRepositoryInterface extends RepositoryInterface
{
    public function create(array $attributes): MediaUpload;

    public function updateStatus(MediaUpload $upload, string $status, ?string $checksum = null): MediaUpload;

    public function findForVideo(string $videoId): ?MediaUpload;
}
