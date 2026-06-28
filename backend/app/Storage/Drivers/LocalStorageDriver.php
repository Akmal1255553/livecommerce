<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

use App\Contracts\Storage\StorageDriverInterface;
use App\DTOs\Storage\PresignedUploadData;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class LocalStorageDriver implements StorageDriverInterface
{
    private const DISK = 'uploads';

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

    public function get(string $path): string
    {
        $contents = $this->disk()->get($path);

        if ($contents === null) {
            throw new \RuntimeException("Object not found: {$path}");
        }

        return $contents;
    }

    public function putFile(string $path, string $localFilePath): void
    {
        $stream = fopen($localFilePath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException("Unable to read local file: {$localFilePath}");
        }

        try {
            $this->disk()->put($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function publicUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/storage/uploads/'.$path;
    }

    public function createPresignedPutUrl(string $path, string $mimeType, int $ttlMinutes): PresignedUploadData
    {
        $expiresAt = now()->addMinutes($ttlMinutes)->toIso8601String();

        return new PresignedUploadData(
            url: rtrim((string) config('app.url'), '/').'/storage/uploads/'.$path,
            method: 'PUT',
            headers: ['Content-Type' => $mimeType],
            expiresAt: $expiresAt,
            storagePath: $path,
        );
    }
}
