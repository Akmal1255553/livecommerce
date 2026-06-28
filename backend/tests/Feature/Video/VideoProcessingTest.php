<?php

declare(strict_types=1);

use App\Enums\MediaAssetType;
use App\Enums\VideoProcessingStepName;
use App\Enums\VideoProcessingStepStatus;
use App\Enums\VideoStatus;
use App\Events\VideoPublished;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use App\Services\Video\Processing\Steps\ValidateVideoStep;
use App\Services\Video\Processing\Steps\VirusScanStep;
use Illuminate\Support\Facades\Event;

test('confirm upload then pipeline publishes video with hls url', function () {
    Event::fake([VideoPublished::class]);

    $user = registerUser('pubuser', 'pubuser@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 2048,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    $video = Video::query()->findOrFail($videoId);

    expect($video->status)->toBe(VideoStatus::Published)
        ->and($video->video_url)->not->toBeNull()
        ->and($video->thumbnail_url)->not->toBeNull()
        ->and($video->duration)->toBeGreaterThan(0);

    Event::assertDispatched(VideoPublished::class);
});

test('published video appears in for you feed', function () {
    $user = registerUser('feedpub', 'feedpub@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 2048,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    test()->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertJsonFragment(['id' => $videoId]);
});

test('pipeline registers thumbnail and hls media assets', function () {
    $user = registerUser('assets', 'assets@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', ['mime_type' => 'video/mp4', 'file_size' => 2048])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    expect(MediaAsset::query()->where('video_id', $videoId)->where('type', MediaAssetType::Thumbnail->value)->exists())->toBeTrue()
        ->and(MediaAsset::query()->where('video_id', $videoId)->where('type', MediaAssetType::HlsMaster->value)->exists())->toBeTrue()
        ->and(MediaAsset::query()->where('video_id', $videoId)->where('type', MediaAssetType::Hls720p->value)->exists())->toBeTrue()
        ->and(MediaAsset::query()->where('video_id', $videoId)->where('type', MediaAssetType::Hls480p->value)->exists())->toBeTrue();
});

test('all mvp processing steps complete after pipeline', function () {
    $user = registerUser('stepsdone', 'stepsdone@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', ['mime_type' => 'video/mp4', 'file_size' => 2048])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    $completed = VideoProcessingStep::query()
        ->where('video_id', $videoId)
        ->where('status', VideoProcessingStepStatus::Completed)
        ->count();

    expect($completed)->toBe(8);
});

test('validate step rejects duration over limit', function () {
    $video = Video::factory()->create([
        'status' => VideoStatus::Processing,
        'duration' => 120,
        'width' => 1080,
        'height' => 1920,
        'codec' => 'h264',
    ]);

    VideoProcessingStep::query()->create([
        'video_id' => $video->id,
        'step' => VideoProcessingStepName::Validate,
        'status' => VideoProcessingStepStatus::Pending,
        'attempt' => 1,
        'created_at' => now(),
    ]);

    try {
        app(ValidateVideoStep::class)->run($video);
    } catch (Throwable) {
        // expected
    }

    $video->refresh();

    expect($video->status)->toBe(VideoStatus::Failed)
        ->and($video->failure_code)->toBe('duration_exceeded');
});

test('completed processing step is not re executed', function () {
    $user = registerUser('idempotent', 'idempotent@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', ['mime_type' => 'video/mp4', 'file_size' => 2048])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    $video = Video::query()->findOrFail($videoId);
    $rowsBefore = VideoProcessingStep::query()->where('video_id', $videoId)->where('step', VideoProcessingStepName::VirusScan->value)->count();

    app(VirusScanStep::class)->run($video);

    expect(VideoProcessingStep::query()->where('video_id', $videoId)->where('step', VideoProcessingStepName::VirusScan->value)->count())
        ->toBe($rowsBefore);
});

test('owner can view failed video with failure code', function () {
    $user = registerUser('failedowner', 'failedowner@example.com');
    $video = Video::factory()->for(User::find($user['user_id']))->create([
        'status' => VideoStatus::Failed,
        'failure_code' => 'transcode_error',
    ]);

    test()->withToken($user['access_token'])
        ->getJson("/api/v1/videos/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.failure_code', 'transcode_error');
});

test('non owner cannot view failed video', function () {
    $owner = registerUser('failown', 'failown@example.com');
    $other = registerUser('failother', 'failother@example.com');

    $video = Video::factory()->for(User::find($owner['user_id']))->create([
        'status' => VideoStatus::Failed,
        'failure_code' => 'transcode_error',
    ]);

    test()->withToken($other['access_token'])
        ->getJson("/api/v1/videos/{$video->id}")
        ->assertNotFound();
});
