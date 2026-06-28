<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Storage\StorageDriverInterface;
use App\DTOs\Storage\PresignedUploadData;
use App\Services\BaseService;
use App\Storage\Drivers\LocalStorageDriver;
use App\Storage\Drivers\S3StorageDriver;

class StorageService extends BaseService implements StorageServiceInterface
{
    public function __construct(
        private readonly LocalStorageDriver $local,
        private readonly S3StorageDriver $s3,
    ) {}

    public function exists(string $path): bool
    {
        return $this->driver()->exists($path);
    }

    public function put(string $path, string $contents): void
    {
        $this->driver()->put($path, $contents);
    }

    public function delete(string $path): bool
    {
        return $this->driver()->delete($path);
    }

    public function createPresignedPutUrl(string $path, string $mimeType, ?int $ttlMinutes = null): PresignedUploadData
    {
        $ttl = $ttlMinutes ?? (int) config('storage.presigned_ttl_minutes', 15);

        return $this->driver()->createPresignedPutUrl($path, $mimeType, $ttl);
    }

    private function driver(): StorageDriverInterface
    {
        return config('storage.driver', 'local') === 's3'
            ? $this->s3
            : $this->local;
    }
}
