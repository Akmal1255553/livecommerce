<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Storage\PresignedUploadData;
use App\Models\MediaUpload;
use App\Models\User;

interface MediaServiceInterface
{
    public function videoRawPath(string $videoId, string $extension): string;

    public function extensionForMime(string $mimeType): string;

    public function createVideoUploadSession(
        User $user,
        string $videoId,
        string $mimeType,
        int $fileSize,
        string $fileName,
    ): PresignedUploadData;

    public function createGenericPresignedUpload(
        User $user,
        string $purpose,
        string $fileName,
        string $mimeType,
        int $fileSize,
    ): PresignedUploadData;

    public function findUploadForVideo(string $videoId): ?MediaUpload;

    public function videoThumbnailPath(string $videoId): string;

    public function videoHlsMasterPath(string $videoId): string;

    public function videoHlsRenditionDir(string $videoId, string $rendition): string;

    public function videoHlsRenditionPlaylistPath(string $videoId, string $rendition): string;
}
