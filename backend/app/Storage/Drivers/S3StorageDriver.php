<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

use App\Contracts\Storage\StorageDriverInterface;
use App\DTOs\Storage\PresignedUploadData;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class S3StorageDriver implements StorageDriverInterface
{
    private const DISK = 's3';

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }

    public function createPresignedPutUrl(string $path, string $mimeType, int $ttlMinutes): PresignedUploadData
    {
        $expiresAt = now()->addMinutes($ttlMinutes);

        try {
            /** @var string $url */
            $url = $this->disk()->temporaryUploadUrl(
                $path,
                $expiresAt,
                ['ContentType' => $mimeType],
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to create presigned upload URL.', 0, $exception);
        }

        return new PresignedUploadData(
            url: $url,
            method: 'PUT',
            headers: ['Content-Type' => $mimeType],
            expiresAt: $expiresAt->toIso8601String(),
            storagePath: $path,
        );
    }
}
