<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\User;
use App\Models\Video;

test('comments list supports pagination', function () {
    $video = Video::factory()->published()->create();
    $author = User::factory()->create();

    foreach (range(1, 25) as $i) {
        Comment::query()->create([
            'user_id' => $author->id,
            'video_id' => $video->id,
            'body' => "Comment {$i}",
        ]);
    }

    test()->getJson("/api/v1/videos/{$video->id}/comments?page=1&per_page=10")
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 25);

    test()->getJson("/api/v1/videos/{$video->id}/comments?page=3&per_page=10")
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('comment body cannot be empty', function () {
    $video = Video::factory()->published()->create();
    $user = registerUser('emptybody', 'emptybody@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", ['body' => '', 'session_id' => analyticsSession()])
        ->assertStatus(422);
});

test('comment body cannot exceed max length', function () {
    $video = Video::factory()->published()->create();
    $user = registerUser('longbody', 'longbody@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => str_repeat('a', 1001),
            'session_id' => analyticsSession(),
        ])
        ->assertStatus(422);
});

test('comments list includes nested replies', function () {
    $video = Video::factory()->published()->create();
    $user = registerUser('nestedlist', 'nestedlist@example.com');

    $parentId = test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", ['body' => 'Parent', 'session_id' => analyticsSession()])
        ->json('data.id');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/videos/{$video->id}/comments", [
            'body' => 'Child reply',
            'parent_id' => $parentId,
            'session_id' => analyticsSession(),
        ])
        ->assertCreated();

    test()->getJson("/api/v1/videos/{$video->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.replies.0.body', 'Child reply');
});
