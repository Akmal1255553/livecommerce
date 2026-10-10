<?php

declare(strict_types=1);

use App\Models\User;

test('anonymous for-you feed is cached for a short ttl', function () {
    publishedVideo();

    $first = test()->getJson('/api/v1/feed/for-you?limit=5');
    $first->assertOk()->assertHeader('X-Cache', 'MISS');

    $second = test()->getJson('/api/v1/feed/for-you?limit=5');
    $second->assertOk()->assertHeader('X-Cache', 'HIT');
});

test('authenticated feed responses are never cached', function () {
    $user = User::factory()->create();
    $auth = test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertOk();
    $token = $auth->json('data.access_token');

    test()->withToken($token)
        ->getJson('/api/v1/feed/for-you')
        ->assertOk()
        ->assertHeaderMissing('X-Cache');
});
