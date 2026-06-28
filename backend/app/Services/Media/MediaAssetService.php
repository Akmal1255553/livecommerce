<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Services\MediaAssetServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MediaAssetService extends BaseService implements MediaAssetServiceInterface
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
    ) {}

    /**
     * @param  array{width?: int, height?: int, byte_size?: int, checksum?: string, metadata?: array<string, mixed>}  $meta
     */
    public function register(
        Video $video,
        MediaAssetType $type,
        string $storagePath,
        string $mimeType,
        array $meta = [],
    ): MediaAsset {
        $asset = MediaAsset::query()->firstOrNew([
            'video_id' => $video->id,
            'type' => $type,
        ]);

        if (! $asset->exists) {
            $asset->id = (string) Str::uuid();
        }

        $asset->fill([
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'byte_size' => $meta['byte_size'] ?? null,
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'checksum' => $meta['checksum'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
            'created_at' => $asset->created_at ?? now(),
        ]);

        $asset->save();

        return $asset;
    }

    public function getPrimary(Video $video, MediaAssetType $type): ?MediaAsset
    {
        return MediaAsset::query()
            ->where('video_id', $video->id)
            ->where('type', $type->value)
            ->first();
    }

    public function listForVideo(Video $video): Collection
    {
        return MediaAsset::query()
            ->where('video_id', $video->id)
            ->orderBy('created_at')
            ->get();
    }

    public function publicUrl(MediaAsset $asset): string
    {
        return $this->storage->publicUrl($asset->storage_path);
    }

    public function resolvePlaybackUrl(Video $video): ?string
    {
        $asset = $this->getPrimary($video, MediaAssetType::HlsMaster);

        return $asset instanceof MediaAsset ? $this->publicUrl($asset) : null;
    }

    public function resolveThumbnailUrl(Video $video): ?string
    {
        $asset = $this->getPrimary($video, MediaAssetType::Thumbnail);

        return $asset instanceof MediaAsset ? $this->publicUrl($asset) : null;
    }
}
