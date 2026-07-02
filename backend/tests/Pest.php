<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * @return array{user_id: string, access_token: string}
 */
function registerUser(string $username, string $email): array
{
    $response = test()->postJson('/api/v1/auth/register', [
        'username' => $username,
        'email' => $email,
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertCreated();

    return [
        'user_id' => $response->json('data.user.id'),
        'access_token' => $response->json('data.access_token'),
    ];
}

function analyticsSession(): string
{
    return (string) Str::uuid();
}

function publishedVideo(?User $owner = null): Video
{
    $owner ??= User::factory()->create();

    return Video::factory()->for($owner)->published()->create([
        'like_count' => 0,
        'comment_count' => 0,
        'share_count' => 0,
        'view_count' => 0,
    ]);
}

/**
 * @return array{user: User, store: Store, token: string}
 */
function createSellerWithStore(): array
{
    $user = User::factory()->seller()->create();
    $store = Store::factory()->for($user)->active()->create();

    $auth = test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertOk();

    return [
        'user' => $user,
        'store' => $store,
        'token' => $auth->json('data.access_token'),
    ];
}
