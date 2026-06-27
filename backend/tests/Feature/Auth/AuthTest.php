<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('user can register with email', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.username', 'johndoe')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'username', 'display_name'],
                'access_token',
                'refresh_token',
                'expires_in',
            ],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    $this->assertDatabaseHas('user_profiles', ['display_name' => 'johndoe']);
});

test('user can register with phone and receives otp flow', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'phoneuser',
        'phone' => '+998901234567',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.phone_verified', false);

    $verify = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '+998901234567',
        'otp' => '123456',
    ]);

    $verify->assertOk()
        ->assertJsonPath('data.user.phone_verified', true);
});

test('user can login with email username or phone', function () {
    User::factory()->create([
        'username' => 'janedoe',
        'email' => 'jane@example.com',
        'phone' => '+998909876543',
        'password' => Hash::make('SecurePass123!'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ])->assertOk()->assertJsonPath('data.user.username', 'janedoe');

    $this->postJson('/api/v1/auth/login', [
        'login' => 'janedoe',
        'password' => 'SecurePass123!',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'login' => '+998909876543',
        'password' => 'SecurePass123!',
    ])->assertOk();
});

test('refresh token rotation works', function () {
    $register = $this->postJson('/api/v1/auth/register', [
        'username' => 'refreshuser',
        'email' => 'refresh@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $oldRefresh = $register->json('data.refresh_token');

    $refresh = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefresh,
    ]);

    $refresh->assertOk()
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in']]);

    $newRefresh = $refresh->json('data.refresh_token');
    expect($newRefresh)->not->toBe($oldRefresh);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefresh,
    ])->assertUnauthorized();
});

test('user can logout and revoke refresh token', function () {
    $register = $this->postJson('/api/v1/auth/register', [
        'username' => 'logoutuser',
        'email' => 'logout@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $access = $register->json('data.access_token');
    $refresh = $register->json('data.refresh_token');

    $this->withToken($access)
        ->postJson('/api/v1/auth/logout', ['refresh_token' => $refresh])
        ->assertOk()
        ->assertJsonPath('data.message', 'Logged out successfully.');

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refresh,
    ])->assertUnauthorized();
});

test('authenticated user can view and update profile', function () {
    $register = $this->postJson('/api/v1/auth/register', [
        'username' => 'profileuser',
        'email' => 'profile@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $token = $register->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'profileuser');

    $this->withToken($token)
        ->putJson('/api/v1/me', [
            'display_name' => 'Profile User',
            'bio' => 'Updated bio',
            'locale' => 'ru',
        ])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'Profile User')
        ->assertJsonPath('data.locale', 'ru');
});

test('password reset flow works', function () {
    User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('OldPass123!'),
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'reset@example.com',
    ])->assertOk();

    $record = DB::table('password_reset_tokens')->where('email', 'reset@example.com')->first();
    expect($record)->not->toBeNull();

    // Token is logged in non-production; use a known token by inserting directly for test
    $plainToken = 'test-reset-token-1234567890';
    DB::table('password_reset_tokens')->where('email', 'reset@example.com')->update([
        'token' => Hash::make($plainToken),
        'created_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $plainToken,
        'password' => 'NewSecure123!',
        'password_confirmation' => 'NewSecure123!',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'login' => 'reset@example.com',
        'password' => 'NewSecure123!',
    ])->assertOk();
});
