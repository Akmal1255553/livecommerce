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
