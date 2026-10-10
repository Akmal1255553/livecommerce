<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use App\Services\Video\VideoProcessingPipelineOrchestrator;

test('upload status requires a valid login and remains visible only to its owner', function () {
    $owner = registerUser('statusowner', 'statusowner@example.com');
    $video = Video::factory()->create(['user_id' => $owner['user_id'], 'status' => VideoStatus::Failed, 'failure_code' => 'duration_exceeded']);
    $url = '/api/v1/videos/'.$video->id.'/upload-status';
    $this->withToken('expired-or-invalid')->getJson($url)->assertUnauthorized();
    $this->withToken($owner['access_token'])->getJson($url)->assertOk()
        ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_code', 'duration_exceeded');
    $other = registerUser('statusother', 'statusother@example.com');
    $this->withToken($other['access_token'])->getJson($url)->assertNotFound();
});

test('retrying a duration rejection cannot leave the video processing again', function () {
    $video = Video::factory()->for(User::factory())->create([
        'status' => VideoStatus::Processing, 'duration' => 109,
        'failure_code' => 'duration_exceeded', 'failure_message' => 'Video exceeds 60 seconds.',
    ]);
    app(VideoProcessingPipelineOrchestrator::class)->run($video->id);
    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
    app(VideoProcessingPipelineOrchestrator::class)->run($video->id);
    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
    expect(VideoProcessingStep::where('video_id', $video->id)->count())->toBe(0);
});

test('owner sees actual processing steps and final publication without cached status', function () {
    $owner = registerUser('progressowner', 'progressowner@example.com');
    $video = Video::factory()->create(['user_id' => $owner['user_id'], 'status' => VideoStatus::Processing]);
    VideoProcessingStep::create(['video_id' => $video->id, 'step' => 'metadata', 'status' => 'completed', 'attempt' => 1, 'created_at' => now()]);
    $url = '/api/v1/videos/'.$video->id.'/upload-status';
    $this->withToken($owner['access_token'])->getJson($url)->assertOk()
        ->assertJsonPath('data.processing_steps.0.status', 'completed');
    $video->update(['status' => VideoStatus::Published]);
    $this->withToken($owner['access_token'])->getJson($url)->assertOk()
        ->assertJsonPath('data.status', 'published');
});

test('pipeline resumes existing progress without creating duplicate steps', function () {
    $video = Video::factory()->for(User::factory())->create([
        'status' => VideoStatus::Queued, 'duration' => 109, 'width' => 1280, 'height' => 720, 'codec' => 'h264',
    ]);
    VideoProcessingStep::create(['video_id' => $video->id, 'step' => 'virus_scan', 'status' => 'completed', 'attempt' => 1, 'created_at' => now()]);
    try {
        app(VideoProcessingPipelineOrchestrator::class)->run($video->id);
    } catch (\App\Exceptions\Domain\VideoProcessingException $error) {
        expect($error->failureCode)->toBe('duration_exceeded');
    }
    expect(VideoProcessingStep::where('video_id', $video->id)->count())->toBe(8);
    expect(VideoProcessingStep::where('video_id', $video->id)->where('step', 'virus_scan')->count())->toBe(1);
    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
});

test('pipeline resumes a step interrupted by a worker restart', function () {
    $video = Video::factory()->for(User::factory())->create([
        'status' => VideoStatus::Processing, 'duration' => 109, 'width' => 1280, 'height' => 720, 'codec' => 'h264',
    ]);
    VideoProcessingStep::create(['video_id' => $video->id, 'step' => 'virus_scan', 'status' => 'completed', 'attempt' => 1, 'created_at' => now()]);
    VideoProcessingStep::create(['video_id' => $video->id, 'step' => 'metadata', 'status' => 'running', 'attempt' => 1, 'created_at' => now(), 'started_at' => now()->subMinutes(11)]);
    try {
        app(VideoProcessingPipelineOrchestrator::class)->run($video->id);
        $this->fail('Expected duration validation to reject the resumed video');
    } catch (\App\Exceptions\Domain\VideoProcessingException $error) {
        expect($error->failureCode)->toBe('duration_exceeded');
    }
    expect(VideoProcessingStep::where('video_id', $video->id)->where('step', 'metadata')->first()->status)
        ->toBe(\App\Enums\VideoProcessingStepStatus::Completed);
    expect(VideoProcessingStep::where('video_id', $video->id)->count())->toBe(8);
    expect($video->fresh()->status)->toBe(VideoStatus::Failed);
});
