<?php

declare(strict_types=1);

use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Enums\VideoProcessingStepName;
use App\Enums\VideoStatus;
use App\Models\EngagementEvent;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProcessingStep;
use Illuminate\Support\Str;

function putVideoRawUpload(string $videoId, string $mimeType = 'video/mp4'): void
{
    $media = app(MediaServiceInterface::class);
    $storage = app(StorageServiceInterface::class);
    $extension = $media->extensionForMime($mimeType);
    $storage->put($media->videoRawPath($videoId, $extension), 'fake-video-binary');
}

test('authenticated user can create video upload session', function () {
    $user = registerUser('uploader1', 'uploader1@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'title' => 'My clip',
            'mime_type' => 'video/mp4',
            'file_size' => 1_048_576,
        ])
        ->assertCreated()
        ->assertJsonPath('data.video.status', VideoStatus::Uploading->value)
        ->assertJsonPath('data.upload_method', 'PUT')
        ->assertJsonStructure([
            'data' => ['video', 'upload_url', 'upload_method', 'upload_headers', 'expires_at'],
        ]);

    test()->assertDatabaseHas('videos', [
        'user_id' => $user['user_id'],
        'status' => VideoStatus::Uploading->value,
    ]);

    test()->assertDatabaseHas('media_uploads', [
        'user_id' => $user['user_id'],
        'entity_type' => 'video',
        'status' => 'pending',
    ]);
});

test('unauthenticated post videos returns unauthorized', function () {
    test()->postJson('/api/v1/videos', [
        'mime_type' => 'video/mp4',
        'file_size' => 1024,
    ])->assertUnauthorized();
});

test('post videos validates mime type and file size', function () {
    $user = registerUser('validuser', 'validuser@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ])
        ->assertStatus(422);

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
        ])
        ->assertStatus(422);
});

test('post videos rejects file size over limit', function () {
    $user = registerUser('bigfile', 'bigfile@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 200_000_000,
        ])
        ->assertStatus(422);
});

test('owner can confirm upload when object exists', function () {
    $user = registerUser('confirmowner', 'confirmowner@example.com');

    $create = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 2048,
        ])
        ->assertCreated();

    $videoId = $create->json('data.video.id');
    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload", [
            'checksum' => 'sha256:abc',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.video.status', VideoStatus::Processing->value)
        ->assertJsonPath('data.message', 'Upload confirmed. Processing started.');

    test()->assertDatabaseHas('media_uploads', [
        'entity_id' => $videoId,
        'status' => 'uploaded',
        'checksum' => 'sha256:abc',
    ]);
});

test('confirm upload fails if not owner', function () {
    $owner = registerUser('ownervid', 'ownervid@example.com');
    $other = registerUser('othervid', 'othervid@example.com');

    $videoId = test()->withToken($owner['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($other['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertForbidden();
});

test('confirm upload fails if status not uploading', function () {
    $user = registerUser('statconflict', 'statconflict@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertStatus(409);
});

test('confirm upload fails if object missing in storage', function () {
    $user = registerUser('missingobj', 'missingobj@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertStatus(422)
        ->assertJsonPath('errors.upload.0', 'Uploaded object was not found in storage.');
});

test('pipeline stub jobs create video processing step rows', function () {
    $user = registerUser('pipeline', 'pipeline@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    expect(VideoProcessingStep::query()->where('video_id', $videoId)->count())->toBe(6)
        ->and(VideoProcessingStep::query()->where('video_id', $videoId)->where('step', VideoProcessingStepName::VirusScan->value)->exists())->toBeTrue();
});

test('after pipeline stubs video stays processing not published', function () {
    $user = registerUser('stayproc', 'stayproc@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    putVideoRawUpload($videoId);

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$videoId}/confirm-upload")
        ->assertAccepted();

    $video = Video::query()->find($videoId);
    expect($video?->status)->toBe(VideoStatus::Processing);
});

test('post metrics events accepts feed_open', function () {
    $sessionId = (string) Str::uuid();

    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'feed_open',
                'session_id' => $sessionId,
                'payload' => ['tab' => 'for_you'],
            ],
        ],
    ])
        ->assertAccepted()
        ->assertJsonPath('data.accepted', 1);

    test()->assertDatabaseHas('engagement_events', [
        'event_type' => 'feed_open',
        'session_id' => $sessionId,
    ]);
});

test('post metrics events accepts video impression with video id', function () {
    $creator = User::factory()->create();
    $video = Video::factory()->for($creator)->published()->create();
    $sessionId = (string) Str::uuid();

    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'video_impression',
                'session_id' => $sessionId,
                'video_id' => $video->id,
                'payload' => ['position' => 0, 'visible_ms' => 500],
            ],
        ],
    ])
        ->assertAccepted()
        ->assertJsonPath('data.accepted', 1);

    expect(EngagementEvent::query()->where('video_id', $video->id)->count())->toBe(1);
});

test('metrics rejects unknown event type in sprint 3.1', function () {
    test()->postJson('/api/v1/metrics/events', [
        'events' => [
            [
                'type' => 'watch_time',
                'session_id' => (string) Str::uuid(),
            ],
        ],
    ])->assertStatus(422);
});

test('get videos returns uploading video for owner', function () {
    $user = registerUser('viewowner', 'viewowner@example.com');

    $videoId = test()->withToken($user['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    test()->withToken($user['access_token'])
        ->getJson("/api/v1/videos/{$videoId}")
        ->assertOk()
        ->assertJsonPath('data.status', VideoStatus::Uploading->value);
});

test('get videos hides non published video from other users', function () {
    $owner = registerUser('hideowner', 'hideowner@example.com');
    $other = registerUser('hideother', 'hideother@example.com');

    $videoId = test()->withToken($owner['access_token'])
        ->postJson('/api/v1/videos', [
            'mime_type' => 'video/mp4',
            'file_size' => 1024,
        ])
        ->json('data.video.id');

    test()->withToken($other['access_token'])
        ->getJson("/api/v1/videos/{$videoId}")
        ->assertNotFound();
});
