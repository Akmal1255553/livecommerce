<?php

declare(strict_types=1);

use App\Storage\Drivers\SupabaseStorageDriver;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('storage.supabase', [
        'bridge_url' => 'https://storage.example/functions/v1/media-storage',
        'token' => 'server-only-secret',
        'public_url' => 'https://storage.example/storage/v1/object/public/livecommerce-media',
    ]);
    Http::preventStrayRequests();
});

test('signed upload exposes only the object scoped URL and content type', function () {
    Http::fake(['*' => Http::response(['url' => 'https://storage.example/object/upload/sign/raw?token=object-token'])]);
    $upload = app(SupabaseStorageDriver::class)->createPresignedPutUrl('videos/id/raw.mp4', 'video/mp4', 15);
    expect($upload->headers)->toBe(['Content-Type' => 'video/mp4']);
    expect($upload->url)->not->toContain('server-only-secret');
    expect($upload->method)->toBe('PUT');
    Http::assertSent(fn ($request) => $request->hasHeader('x-media-token', 'server-only-secret') && str_contains($request->url(), 'op=sign'));
});

test('raw uploads cannot be published as public URLs', function () {
    app(SupabaseStorageDriver::class)->publicUrl('videos/id/raw.mp4');
})->throws(RuntimeException::class, 'Raw video is private.');

test('storage outages are not misreported as missing uploads', function () {
    Http::fake(['*' => Http::response(['error' => 'unavailable'], 502)]);
    app(SupabaseStorageDriver::class)->exists('videos/id/raw.mp4');
})->throws(Illuminate\Http\Client\RequestException::class);

test('HLS playlists retain the browser playback content type', function () {
    Http::fake(['*' => Http::response([], 200)]);
    app(SupabaseStorageDriver::class)->put('videos/id/hls/master.m3u8', '#EXTM3U');
    Http::assertSent(fn ($request) => $request->hasHeader('Content-Type', 'application/vnd.apple.mpegurl') && $request->body() === '#EXTM3U');
});
