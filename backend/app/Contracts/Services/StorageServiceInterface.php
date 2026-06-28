<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Storage\PresignedUploadData;

interface StorageServiceInterface
{
    public function exists(string $path): bool;

    public function put(string $path, string $contents): void;

    public function delete(string $path): bool;

    public function createPresignedPutUrl(string $path, string $mimeType, ?int $ttlMinutes = null): PresignedUploadData;
}
