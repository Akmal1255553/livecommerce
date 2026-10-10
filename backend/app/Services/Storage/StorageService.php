<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Storage\StorageDriverInterface;
use App\DTOs\Storage\PresignedUploadData;
use App\Services\BaseService;
use App\Storage\Drivers\LocalStorageDriver;
use App\Storage\Drivers\S3StorageDriver;
use App\Storage\Drivers\SupabaseStorageDriver;

class StorageService extends BaseService implements StorageServiceInterface
{
    public function __construct(
        private readonly LocalStorageDriver $local,
        private readonly S3StorageDriver $s3,
        private readonly SupabaseStorageDriver $supabase,
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

    public function get(string $path): string
    {
        return $this->driver()->get($path);
    }

    public function putFile(string $path, string $localFilePath): void
    {
        $this->driver()->putFile($path, $localFilePath);
    }

    public function publicUrl(string $path): string
    {
        return $this->driver()->publicUrl($path);
    }

    /** @param array<string, string> $files */
    public function putFiles(array $files): void
    {
        if (config('storage.driver') === 'supabase') {
            $this->supabase->putFiles($files);
            return;
        }
        foreach ($files as $path => $localPath) $this->putFile($path, $localPath);
    }

    public function downloadToTemp(string $path): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'lc_vid_');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create temporary file.');
        }

        file_put_contents($tempPath, $this->get($path));

        return $tempPath;
    }

    private function driver(): StorageDriverInterface
    {
        return match (config('storage.driver', 'local')) {
            's3' => $this->s3,
            'supabase' => $this->supabase,
            default => $this->local,
        };
    }
}
