<?php

use App\Models\User;
use App\Models\Video;

test('feed follow state persists and clears after unfollow', function () {
    $viewer = registerUser('feedfollower', 'feedfollower@example.com');
    $author = User::factory()->create();
    $video = Video::factory()->for($author)->published()->create();
    $url = '/api/v1/videos/'.$video->id;
    $this->withToken($viewer['access_token'])->getJson($url)->assertOk()->assertJsonPath('data.is_following', false);
    $this->withToken($viewer['access_token'])->postJson('/api/v1/users/'.$author->id.'/follow')->assertCreated();
    $this->withToken($viewer['access_token'])->getJson($url)->assertOk()->assertJsonPath('data.is_following', true);
    $this->withToken($viewer['access_token'])->getJson('/api/v1/feed/following')->assertOk()->assertJsonPath('data.0.is_following', true);
    $this->withToken($viewer['access_token'])->deleteJson('/api/v1/users/'.$author->id.'/follow')->assertOk();
    $this->withToken($viewer['access_token'])->getJson($url)->assertOk()->assertJsonPath('data.is_following', false);
});
