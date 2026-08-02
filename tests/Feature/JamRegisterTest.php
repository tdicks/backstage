<?php

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
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
        'allow_checkins' => false,
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

test('new jam sessions receive a unique four character register code', function () {
    $firstSession = createJamSession();
    $secondSession = createJamSession(['name' => 'Saturday Jam']);

    expect($firstSession->jam_register_code)
        ->toMatch('/^[A-Za-z0-9]{4}$/')
        ->not->toBe($secondSession->jam_register_code);
});

test('a jam register code opens a preselected session', function () {
    $session = createJamSession([
        'allow_checkins' => true,
        'jam_register_code' => 'Ab12',
    ]);

    $this->get(route('jam-register.session', $session->jam_register_code))
        ->assertOk()
        ->assertSee('selectedSessionId: '.$session->id)
        ->assertSee('showSessionPicker: false')
        ->assertSee('cameFromShortCode: true')
        ->assertSee('sessionUrl:')
        ->assertSee('registerUrl:')
        ->assertSeeText("Can't find your name? Register a Backstage account here!")
        ->assertSee('x-show="cameFromShortCode && !showSessionLink"', false)
        ->assertSee('x-show="suggestions.length > 0"', false)
        ->assertSee('class="mt-3 block text-center text-sm font-medium text-slate-600', false)
        ->assertSee($session->getRouteKey());
});

test('a jam register code is unavailable when sign-ins are disabled', function () {
    $session = createJamSession([
        'allow_checkins' => false,
        'jam_register_code' => 'Cd34',
    ]);

    $this->get(route('jam-register.session', $session->jam_register_code))
        ->assertNotFound();
});

test('it signs a user in and reports status', function () {
    $session = createJamSession(['allow_checkins' => true]);
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
    expect($user->refresh()->last_seen_at)
        ->not->toBeNull()
        ->and($user->last_signed_in_at)->not->toBeNull();

    $statusResponse = $this->getJson(route('jam-register.status', [$session, $user]));

    $statusResponse->assertOk();
    $statusResponse->assertJsonPath('signed_in', true);
    $statusResponse->assertJsonPath('user.id', $user->id);
});

test('signing in marks maybe attendance as going', function () {
    $session = createJamSession(['allow_checkins' => true]);
    $user = User::factory()->create();

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_MAYBE,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now()->subHour(),
    ]);

    $this->postJson(route('jam-register.sign-in', $session), [
        'user_id' => $user->id,
    ])->assertOk();

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_GOING)
        ->and($attendance->source)->toBe(JamSessionAttendance::SOURCE_AUTO_SIGN_IN);
});

test('signing in marks not going attendance as going', function () {
    $session = createJamSession(['allow_checkins' => true]);
    $user = User::factory()->create();

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now()->subHour(),
    ]);

    $this->postJson(route('jam-register.sign-in', $session), [
        'user_id' => $user->id,
    ])->assertOk();

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_GOING)
        ->and($attendance->source)->toBe(JamSessionAttendance::SOURCE_AUTO_SIGN_IN);
});

test('signing in does not change attendance when already going', function () {
    $session = createJamSession(['allow_checkins' => true]);
    $user = User::factory()->create();

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now()->subHour(),
    ]);

    $this->postJson(route('jam-register.sign-in', $session), [
        'user_id' => $user->id,
    ])->assertOk();

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_GOING)
        ->and($attendance->source)->toBe(JamSessionAttendance::SOURCE_SELF);
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
    $session = createJamSession(['allow_checkins' => true]);
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
    $attendeesResponse->assertJsonPath('attendees.0.name', 'Alice');
    $attendeesResponse->assertJsonPath('attendees.1.name', 'Bob');

    $signOutAllResponse = $this->actingAs($admin)
        ->postJson(route('sessions.check-ins.sign-out-all', $session));

    $signOutAllResponse->assertOk();
    $signOutAllResponse->assertJsonPath('count', 2);

    $this->assertDatabaseCount('jam_session_sign_ins', 0);
});

test('admin can check out an individual attendee', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession(['allow_checkins' => true]);
    $attendee = User::factory()->create();

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $attendee->id,
        'signed_in_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('sessions.check-ins.sign-out', [$session, $attendee]));

    $response->assertOk();
    $response->assertJsonPath('signed_in', false);

    $this->assertDatabaseMissing('jam_session_sign_ins', [
        'jam_session_id' => $session->id,
        'user_id' => $attendee->id,
    ]);
});

test('admin can search users who are not checked in and manually check one in', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = createJamSession(['allow_checkins' => true]);
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

    $this->actingAs($member)
        ->postJson(route('sessions.check-ins.sign-out', [$session, User::factory()->create()]))
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
        ->assertSee('This action will sign out all attendees from this session.');
});
