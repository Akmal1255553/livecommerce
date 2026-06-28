<?php

declare(strict_types=1);

use App\Contracts\Services\StorageServiceInterface;
use App\Services\Storage\StorageService;

test('storage service puts and checks existence via local driver', function () {
    /** @var StorageService $storage */
    $storage = app(StorageServiceInterface::class);
    $path = 'tests/sample-'.uniqid().'.txt';

    expect($storage->exists($path))->toBeFalse();

    $storage->put($path, 'hello-storage');

    expect($storage->exists($path))->toBeTrue();

    $storage->delete($path);

    expect($storage->exists($path))->toBeFalse();
});

test('storage service creates presigned upload metadata', function () {
    $storage = app(StorageServiceInterface::class);

    $presigned = $storage->createPresignedPutUrl('videos/test-id/raw.mp4', 'video/mp4', 15);

    expect($presigned->url)->not->toBeEmpty()
        ->and($presigned->method)->toBe('PUT')
        ->and($presigned->headers)->toHaveKey('Content-Type')
        ->and($presigned->storagePath)->toBe('videos/test-id/raw.mp4');
});
