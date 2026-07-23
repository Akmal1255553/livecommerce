<?php

declare(strict_types=1);

use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\User;

function loginAsAdmin(): array
{
    $admin = User::factory()->admin()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $admin->email,
        'password' => 'password',
    ])->assertOk()->json('data.access_token');

    return ['user' => $admin, 'token' => $token];
}

test('non-admin cannot access admin users', function () {
    $user = registerUser('notadmin', 'notadmin@example.com');

    test()->withToken($user['access_token'])
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

test('admin can list users and suspend ban activate', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create(['username' => 'toban']);

    test()->withToken($admin['token'])
        ->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/users/{$target->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', UserStatus::Suspended->value);

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/users/{$target->id}/ban")
        ->assertOk()
        ->assertJsonPath('data.status', UserStatus::Banned->value);

    test()->assertDatabaseHas('audit_logs', [
        'action' => 'user.ban',
        'entity_id' => $target->id,
    ]);

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/users/{$target->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', UserStatus::Active->value);
});

test('seller apply is pending until admin approves', function () {
    $auth = registerUser('pendingseller', 'pendingseller@example.com');

    $storeId = test()->withToken($auth['access_token'])
        ->postJson('/api/v1/seller/apply', [
            'store_name' => 'Pending Shop',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', StoreStatus::Pending->value)
        ->json('data.id');

    test()->withToken($auth['access_token'])
        ->getJson('/api/v1/seller/dashboard')
        ->assertForbidden();

    $admin = loginAsAdmin();

    test()->withToken($admin['token'])
        ->getJson('/api/v1/admin/stores/pending')
        ->assertOk()
        ->assertJsonPath('data.0.id', $storeId);

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/stores/{$storeId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', StoreStatus::Active->value);

    test()->withToken($auth['access_token'])
        ->getJson('/api/v1/seller/dashboard')
        ->assertOk();
});

test('user can report content and admin can resolve', function () {
    $reporter = registerUser('reporter1', 'reporter1@example.com');
    $target = User::factory()->create();
    $admin = loginAsAdmin();

    $reportId = test()->withToken($reporter['access_token'])
        ->postJson('/api/v1/reports', [
            'target_type' => 'user',
            'target_id' => $target->id,
            'reason' => 'Spam account',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    test()->withToken($admin['token'])
        ->getJson('/api/v1/admin/reports')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reportId);

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/reports/{$reportId}/resolve", [
            'resolution_note' => 'Warned user',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');
});

test('admin can create category', function () {
    $admin = loginAsAdmin();

    test()->withToken($admin['token'])
        ->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Electronics')
        ->assertJsonPath('data.slug', 'electronics');
});

test('admin category mutation invalidates categories tree cache', function () {
    $admin = loginAsAdmin();

    Category::factory()->create(['name' => 'Cached Parent', 'slug' => 'cached-parent']);

    test()->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'cached-parent']);

    test()->withToken($admin['token'])
        ->postJson('/api/v1/admin/categories', [
            'name' => 'After Cache',
            'slug' => 'after-cache',
        ])
        ->assertCreated();

    test()->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'cached-parent'])
        ->assertJsonFragment(['slug' => 'after-cache']);
});

test('admin cannot suspend themselves', function () {
    $admin = loginAsAdmin();

    test()->withToken($admin['token'])
        ->putJson("/api/v1/admin/users/{$admin['user']->id}/suspend")
        ->assertForbidden();
});
