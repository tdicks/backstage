<?php

use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createJamSession(array $overrides = []): JamSession
{
    return JamSession::query()->create(array_merge([
        'name' => 'Friday Jam',
        'date' => now()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ], $overrides));
}

test('it returns matching users for jam register search', function () {
    User::factory()->create(['name' => 'Alice Player']);
    User::factory()->create(['name' => 'Bob Drummer']);

    $response = $this->getJson(route('jam-register.users', ['q' => 'ali']));

    $response->assertOk();
    $response->assertJsonCount(1, 'users');
    $response->assertJsonPath('users.0.name', 'Alice Player');
});

test('it signs a user in and reports status', function () {
    $session = createJamSession();
    $user = User::factory()->create();

    $signInResponse = $this->postJson(route('jam-register.sign-in', $session), [
        'user_id' => $user->id,
    ]);

    $signInResponse->assertOk();
    $signInResponse->assertJsonPath('signed_in', true);

    $this->assertDatabaseHas('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
    ]);

    $statusResponse = $this->getJson(route('jam-register.status', [$session, $user]));

    $statusResponse->assertOk();
    $statusResponse->assertJsonPath('signed_in', true);
    $statusResponse->assertJsonPath('user.id', $user->id);
});

test('it signs a user out', function () {
    $session = createJamSession();
    $user = User::factory()->create();

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'signed_in_at' => now(),
    ]);

    $response = $this->postJson(route('jam-register.sign-out', [$session, $user]), [
        'user_id' => $user->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('signed_in', false);

    $this->assertDatabaseMissing('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
    ]);
});

test('admin can see attendees and sign everyone out', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $alice->id,
        'signed_in_at' => now()->subMinutes(2),
    ]);
    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $bob->id,
        'signed_in_at' => now()->subMinute(),
    ]);

    $attendeesResponse = $this->actingAs($admin)
        ->getJson(route('sessions.check-ins', $session));

    $attendeesResponse->assertOk();
    $attendeesResponse->assertJsonPath('count', 2);

    $signOutAllResponse = $this->actingAs($admin)
        ->postJson(route('sessions.check-ins.sign-out-all', $session));

    $signOutAllResponse->assertOk();
    $signOutAllResponse->assertJsonPath('count', 2);

    $this->assertDatabaseCount('jam_session_sign_ins', 0);
});

test('admin can search users who are not checked in and manually check one in', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession();
    $checkedIn = User::factory()->create(['name' => 'Alice Checked In']);
    $available = User::factory()->create(['name' => 'Alice Available']);
    User::factory()->create(['name' => 'Bob Elsewhere']);

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $checkedIn->id,
        'signed_in_at' => now(),
    ]);

    $lookupResponse = $this->actingAs($admin)
        ->getJson(route('sessions.check-ins.users', [$session, 'q' => 'Alice']));

    $lookupResponse->assertOk();
    $lookupResponse->assertJsonCount(1, 'users');
    $lookupResponse->assertJsonPath('users.0.id', $available->id);
    $lookupResponse->assertJsonPath('users.0.name', 'Alice Available');

    $signInResponse = $this->actingAs($admin)
        ->postJson(route('sessions.check-ins.sign-in', $session), [
            'user_id' => $available->id,
        ]);

    $signInResponse->assertOk();
    $signInResponse->assertJsonPath('signed_in', true);
    $signInResponse->assertJsonPath('sign_in.user_id', $available->id);

    $this->assertDatabaseHas('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $available->id,
    ]);
});

test('non admin cannot access admin check-in endpoints', function () {
    $member = User::factory()->create(['is_admin' => false]);
    $session = createJamSession();

    $this->actingAs($member)
        ->getJson(route('sessions.check-ins', $session))
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('sessions.check-ins.sign-out-all', $session))
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('sessions.check-ins.users', $session))
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('sessions.check-ins.sign-in', $session), [
            'user_id' => User::factory()->create()->id,
        ])
        ->assertForbidden();
});

test('closed jam sessions cannot be checked into', function () {
    $session = createJamSession([
        'is_closed' => true,
        'allow_checkins' => true,
    ]);
    $user = User::factory()->create();

    $this->postJson(route('jam-register.sign-in', $session), [
        'user_id' => $user->id,
    ])->assertForbidden();

    $this->assertDatabaseMissing('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
    ]);
});

test('closing a jam session automatically disables check-ins', function () {
    $session = createJamSession([
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $session->update([
        'is_closed' => true,
        'allow_checkins' => true,
    ]);

    expect($session->refresh()->allow_checkins)->toBeFalse();
});

test('disabling jam session check-ins signs everyone out', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession([
        'is_closed' => false,
        'allow_checkins' => true,
    ]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $alice->id,
        'signed_in_at' => now()->subMinutes(2),
    ]);
    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $bob->id,
        'signed_in_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->patch(route('sessions.update', $session), [
            'name' => $session->name,
            'date' => $session->date->toDateString(),
            'description' => $session->description,
            'is_closed' => false,
            'allow_checkins' => false,
        ])
        ->assertRedirect();

    expect($session->refresh()->allow_checkins)->toBeFalse();
    $this->assertDatabaseMissing('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $alice->id,
    ]);
    $this->assertDatabaseMissing('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $bob->id,
    ]);
});

test('edit jam session form warns when disabling check-ins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession([
        'allow_checkins' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('This action will check out all attendees from this session.');
});
