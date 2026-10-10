<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

use App\Contracts\Storage\StorageDriverInterface;
use App\DTOs\Storage\PresignedUploadData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseStorageDriver implements StorageDriverInterface
{
    private function client(): PendingRequest
    {
        $token = (string) config('storage.supabase.token');
        if ($token === '') {
            throw new RuntimeException('Supabase storage is not configured.');
        }

        return Http::withHeaders(['x-media-token' => $token])->timeout(120)->connectTimeout(15);
    }

    private function endpoint(string $operation, string $path): string
    {
        return rtrim((string) config('storage.supabase.bridge_url'), '/').'?'.http_build_query(['op' => $operation, 'path' => $path]);
    }

    public function exists(string $path): bool
    {
        return $this->client()->get($this->endpoint('exists', $path))->throw()->json('exists') === true;
    }

    public function put(string $path, string $contents): void
    {
        $this->client()->withBody($contents, $this->mime($path))->put($this->endpoint('write', $path))->throw();
    }

    public function delete(string $path): bool
    {
        $this->client()->delete($this->endpoint('delete', $path))->throw();

        return true;
    }

    public function get(string $path): string
    {
        return $this->client()->get($this->endpoint('read', $path))->throw()->body();
    }

    public function putFile(string $path, string $localFilePath): void
    {
        $stream = fopen($localFilePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read media file.');
        }
        try {
            $this->client()->withBody($stream, $this->mime($path))->put($this->endpoint('write', $path))->throw();
        } finally {
            fclose($stream);
        }
    }

    public function publicUrl(string $path): string
    {
        if (preg_match('~/raw\.(mp4|mov|webm)$~', $path)) {
            throw new RuntimeException('Raw video is private.');
        }

        return rtrim((string) config('storage.supabase.public_url'), '/').'/'.$path;
    }

    /** @param array<string, string> $files Storage paths mapped to local files. */
    public function putFiles(array $files): void
    {
        // Keep memory bounded while uploading independent HLS segments concurrently.
        foreach (array_chunk($files, 3, true) as $batch) {
            $streams = [];
            try {
                foreach ($batch as $path => $localPath) {
                    $stream = fopen($localPath, 'rb');
                    if ($stream === false) throw new RuntimeException('Unable to read media file.');
                    $streams[$path] = $stream;
                }
                $responses = Http::pool(function (Pool $pool) use ($streams): array {
                    $requests = [];
                    foreach ($streams as $path => $stream) {
                        $requests[] = $pool->as($path)
                            ->withHeaders(['x-media-token' => (string) config('storage.supabase.token')])
                            ->timeout(120)->connectTimeout(15)->withBody($stream, $this->mime($path))
                            ->put($this->endpoint('write', $path));
                    }
                    return $requests;
                });
                foreach ($responses as $response) {
                    if (! $response instanceof Response) throw $response;
                    $response->throw();
                }
            } finally {
                foreach ($streams as $stream) fclose($stream);
            }
        }
    }

    public function createPresignedPutUrl(string $path, string $mimeType, int $ttlMinutes): PresignedUploadData
    {
        $url = $this->client()->post($this->endpoint('sign', $path))->throw()->json('url');
        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new RuntimeException('Invalid signed upload URL.');
        }

        return new PresignedUploadData($url, 'PUT', ['Content-Type' => $mimeType], now()->addHours(2)->toIso8601String(), $path);
    }

    private function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
