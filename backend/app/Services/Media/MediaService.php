<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\DTOs\Storage\PresignedUploadData;
use App\Enums\MediaUploadStatus;
use App\Models\MediaUpload;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaService extends BaseService implements MediaServiceInterface
{
    private const VIDEO_MIMES = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly MediaUploadRepositoryInterface $uploads,
    ) {}

    public function videoRawPath(string $videoId, string $extension): string
    {
        return 'videos/'.$videoId.'/raw.'.$extension;
    }

    public function extensionForMime(string $mimeType): string
    {
        if (! isset(self::VIDEO_MIMES[$mimeType])) {
            throw new InvalidArgumentException('Unsupported mime type.');
        }

        return self::VIDEO_MIMES[$mimeType];
    }

    public function createVideoUploadSession(
        User $user,
        string $videoId,
        string $mimeType,
        int $fileSize,
        string $fileName,
    ): PresignedUploadData {
        $extension = $this->extensionForMime($mimeType);
        $path = $this->videoRawPath($videoId, $extension);

        $this->uploads->create([
            'user_id' => $user->id,
            'entity_type' => 'video',
            'entity_id' => $videoId,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'storage_path' => $path,
            'status' => MediaUploadStatus::Pending,
        ]);

        $presigned = $this->storage->createPresignedPutUrl($path, $mimeType);

        return new PresignedUploadData(
            url: $presigned->url,
            method: $presigned->method,
            headers: $presigned->headers,
            expiresAt: $presigned->expiresAt,
            storagePath: $path,
        );
    }

    public function createGenericPresignedUpload(
        User $user,
        string $purpose,
        string $fileName,
        string $mimeType,
        int $fileSize,
    ): PresignedUploadData {
        $uploadId = (string) Str::uuid();
        $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'bin';
        $path = $purpose.'/'.$user->id.'/'.$uploadId.'.'.$extension;

        $this->uploads->create([
            'user_id' => $user->id,
            'entity_type' => $purpose,
            'entity_id' => $uploadId,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'storage_path' => $path,
            'status' => MediaUploadStatus::Pending,
        ]);

        return $this->storage->createPresignedPutUrl($path, $mimeType);
    }

    public function findUploadForVideo(string $videoId): ?MediaUpload
    {
        return $this->uploads->findForVideo($videoId);
    }
}
