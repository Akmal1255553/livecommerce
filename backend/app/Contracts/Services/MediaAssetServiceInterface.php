<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use App\Models\Video;
use Illuminate\Support\Collection;

interface MediaAssetServiceInterface
{
    /**
     * @param  array{width?: int, height?: int, byte_size?: int, checksum?: string, metadata?: array<string, mixed>}  $meta
     */
    public function register(
        Video $video,
        MediaAssetType $type,
        string $storagePath,
        string $mimeType,
        array $meta = [],
    ): MediaAsset;

    public function getPrimary(Video $video, MediaAssetType $type): ?MediaAsset;

    /**
     * @return Collection<int, MediaAsset>
     */
    public function listForVideo(Video $video): Collection;

    public function publicUrl(MediaAsset $asset): string;

    public function resolvePlaybackUrl(Video $video): ?string;

    public function resolveThumbnailUrl(Video $video): ?string;
}
