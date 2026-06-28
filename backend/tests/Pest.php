<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
