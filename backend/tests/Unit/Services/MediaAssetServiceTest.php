<?php

declare(strict_types=1);

use App\Contracts\Services\MediaAssetServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('media asset register upserts by video and type', function () {
    $video = Video::factory()->create();
    $storage = app(StorageServiceInterface::class);
    $assets = app(MediaAssetServiceInterface::class);

    $storage->put('videos/'.$video->id.'/thumb.jpg', 'thumb');

    $first = $assets->register($video, MediaAssetType::Thumbnail, 'videos/'.$video->id.'/thumb.jpg', 'image/jpeg');
    $second = $assets->register($video, MediaAssetType::Thumbnail, 'videos/'.$video->id.'/thumb.jpg', 'image/jpeg', [
        'byte_size' => 100,
    ]);

    expect($first->id)->toBe($second->id)
        ->and($second->byte_size)->toBe(100)
        ->and(MediaAsset::query()->where('video_id', $video->id)->count())->toBe(1);
});

test('media asset public url delegates to storage service', function () {
    $video = Video::factory()->create();
    $assets = app(MediaAssetServiceInterface::class);

    $asset = $assets->register($video, MediaAssetType::HlsMaster, 'videos/'.$video->id.'/hls/master.m3u8', 'application/vnd.apple.mpegurl');

    expect($assets->publicUrl($asset))->toContain('master.m3u8');
});
