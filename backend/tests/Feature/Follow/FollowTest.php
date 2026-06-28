<?php

declare(strict_types=1);

use App\Models\User;

test('user can follow another user', function () {
    $follower = registerUser('follower1', 'follower1@example.com');
    $target = User::factory()->create(['username' => 'targetuser']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'User followed successfully.');

    test()->assertDatabaseHas('follows', [
        'follower_id' => $follower['user_id'],
        'following_id' => $target->id,
    ]);
});

test('follower and following counts update correctly', function () {
    $follower = registerUser('counterfollower', 'counterfollower@example.com');
    $target = User::factory()->create(['username' => 'countertarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    $target->profile->refresh();
    $followerProfile = User::query()->find($follower['user_id'])?->profile;
    $followerProfile?->refresh();

    expect($target->profile->follower_count)->toBe(1)
        ->and($followerProfile?->following_count)->toBe(1);
});

test('user cannot follow themselves', function () {
    $user = registerUser('selffollow', 'selffollow@example.com');

    test()->withToken($user['access_token'])
        ->postJson("/api/v1/users/{$user['user_id']}/follow")
        ->assertForbidden();
});

test('duplicate follow returns conflict', function () {
    $follower = registerUser('dupfollower', 'dupfollower@example.com');
    $target = User::factory()->create(['username' => 'duptarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

test('repeated follow does not duplicate row or change counters', function () {
    $follower = registerUser('repeatfollower', 'repeatfollower@example.com');
    $target = User::factory()->create(['username' => 'repeattarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    $target->profile->refresh();
    $followerProfile = User::query()->find($follower['user_id'])?->profile;
    $followerProfile?->refresh();

    expect($target->profile->follower_count)->toBe(1)
        ->and($followerProfile?->following_count)->toBe(1);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Already following this user.');

    test()->assertDatabaseCount('follows', 1);
    test()->assertDatabaseHas('follows', [
        'follower_id' => $follower['user_id'],
        'following_id' => $target->id,
    ]);

    $target->profile->refresh();
    $followerProfile?->refresh();

    expect($target->profile->follower_count)->toBe(1)
        ->and($followerProfile?->following_count)->toBe(1);
});

test('follow rolls back atomically when target profile is missing', function () {
    $follower = registerUser('atomicfollower', 'atomicfollower@example.com');
    $target = User::factory()->create(['username' => 'atomictarget']);

    $target->profile()->delete();

    $response = test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow");

    $response->assertJsonPath('success', false);

    test()->assertDatabaseMissing('follows', [
        'follower_id' => $follower['user_id'],
        'following_id' => $target->id,
    ]);

    $followerProfile = User::query()->find($follower['user_id'])?->profile;
    $followerProfile?->refresh();

    expect($followerProfile?->following_count)->toBe(0);
});

test('unfollow rolls back atomically when follower profile is missing', function () {
    $follower = registerUser('atomicunfollower', 'atomicunfollower@example.com');
    $target = User::factory()->create(['username' => 'atomicunfollowtarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    User::query()->find($follower['user_id'])?->profile()?->delete();

    $response = test()->withToken($follower['access_token'])
        ->deleteJson("/api/v1/users/{$target->id}/follow");

    $response->assertJsonPath('success', false);

    test()->assertDatabaseHas('follows', [
        'follower_id' => $follower['user_id'],
        'following_id' => $target->id,
    ]);

    $target->profile->refresh();

    expect($target->profile->follower_count)->toBe(1);
});

test('user can unfollow another user', function () {
    $follower = registerUser('unfollower', 'unfollower@example.com');
    $target = User::factory()->create(['username' => 'unfollowtarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->withToken($follower['access_token'])
        ->deleteJson("/api/v1/users/{$target->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.message', 'User unfollowed successfully.');

    test()->assertDatabaseMissing('follows', [
        'follower_id' => $follower['user_id'],
        'following_id' => $target->id,
    ]);
});

test('unfollow when not following returns not found', function () {
    $follower = registerUser('notfollowing', 'notfollowing@example.com');
    $target = User::factory()->create(['username' => 'notfollowingtarget']);

    test()->withToken($follower['access_token'])
        ->deleteJson("/api/v1/users/{$target->id}/follow")
        ->assertNotFound();
});

test('unfollow decrements follower and following counts', function () {
    $follower = registerUser('decfollower', 'decfollower@example.com');
    $target = User::factory()->create(['username' => 'dectarget']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->withToken($follower['access_token'])
        ->deleteJson("/api/v1/users/{$target->id}/follow")
        ->assertOk();

    $target->profile->refresh();
    $followerProfile = User::query()->find($follower['user_id'])?->profile;
    $followerProfile?->refresh();

    expect($target->profile->follower_count)->toBe(0)
        ->and($followerProfile?->following_count)->toBe(0);
});

test('public profile can be viewed without auth', function () {
    $target = User::factory()->create([
        'username' => 'publicuser',
        'bio' => 'Hello world',
    ]);

    test()->getJson("/api/v1/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.username', 'publicuser')
        ->assertJsonPath('data.bio', 'Hello world')
        ->assertJsonMissingPath('data.is_following');
});

test('public profile includes is_following for authenticated viewer', function () {
    $follower = registerUser('viewerfollow', 'viewerfollow@example.com');
    $target = User::factory()->create(['username' => 'vieweduser']);

    test()->withToken($follower['access_token'])
        ->getJson("/api/v1/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.is_following', false);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->withToken($follower['access_token'])
        ->getJson("/api/v1/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.is_following', true);
});

test('followers list returns paginated users', function () {
    $target = User::factory()->create(['username' => 'popularuser']);
    $followerA = registerUser('followera', 'followera@example.com');
    $followerB = registerUser('followerb', 'followerb@example.com');

    test()->withToken($followerA['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->withToken($followerB['access_token'])
        ->postJson("/api/v1/users/{$target->id}/follow")
        ->assertCreated();

    test()->getJson("/api/v1/users/{$target->id}/followers?per_page=10")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
});

test('following list returns paginated users', function () {
    $follower = registerUser('listfollower', 'listfollower@example.com');
    $targetA = User::factory()->create(['username' => 'targeta']);
    $targetB = User::factory()->create(['username' => 'targetb']);

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$targetA->id}/follow")
        ->assertCreated();

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$targetB->id}/follow")
        ->assertCreated();

    test()->getJson("/api/v1/users/{$follower['user_id']}/following")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
});

test('follow requires authentication', function () {
    $target = User::factory()->create();

    test()->postJson("/api/v1/users/{$target->id}/follow")
        ->assertUnauthorized();
});

test('follow non-existent user returns not found', function () {
    $follower = registerUser('missingtarget', 'missingtarget@example.com');
    $missingId = '00000000-0000-4000-8000-000000000099';

    test()->withToken($follower['access_token'])
        ->postJson("/api/v1/users/{$missingId}/follow")
        ->assertNotFound();
});
