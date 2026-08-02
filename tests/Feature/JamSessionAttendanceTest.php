<?php

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use App\Services\JamSessionAttendanceService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAttendanceSession(array $overrides = []): JamSession
{
    return JamSession::query()->create(array_merge([
        'name' => 'Attendance Session',
        'date' => now()->addDay()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
        'allow_checkins' => false,
        'is_live' => false,
    ], $overrides));
}

function makeAttendanceSet(User $owner, JamSession $session, array $overrides = []): Set
{
    return Set::query()->create(array_merge([
        'name' => 'Attendance Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'is_hidden' => false,
        'song_requests' => true,
        'free_for_all' => false,
    ], $overrides));
}

function makeAttendanceSlot(Set $set, string $slotName = 'vocals'): Slot
{
    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'The Artist',
        'title' => 'The Song',
        'notes' => null,
        'position' => 1,
    ]);

    return Slot::query()->create([
        'song_id' => $song->id,
        'name' => $slotName,
        'notes' => null,
        'position' => 1,
    ]);
}

test('attendance update does not require dropout action when user has no sets or slots', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession();

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
        ])
        ->assertOk()
        ->assertJsonPath('status', JamSessionAttendance::STATUS_NOT_GOING);
});

test('attendance updates are blocked when session is closed', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession([
        'is_closed' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_GOING,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Attendance cannot be changed on a closed session.');
});

test('attendance index lists only going and not going users', function () {
    $viewer = User::factory()->create();
    $going = User::factory()->create(['name' => 'Aaron Going']);
    $notGoing = User::factory()->create(['name' => 'Bella Not Going']);
    $maybe = User::factory()->create(['name' => 'Cara Maybe']);
    $session = makeAttendanceSession();

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $going->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $notGoing->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $maybe->id,
        'status' => JamSessionAttendance::STATUS_MAYBE,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $response = $this->actingAs($viewer)
        ->getJson(route('sessions.attendance.index', $session))
        ->assertOk();

    $names = collect($response->json('users'))->pluck('name')->all();

    expect($names)->toContain('Aaron Going');
    expect($names)->toContain('Bella Not Going');
    expect($names)->not->toContain('Cara Maybe');
});

test('admin can override another users attendance', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $session = makeAttendanceSession();

    $this->actingAs($admin)
        ->postJson(route('sessions.attendance.update', $session), [
            'user_id' => $target->id,
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
        ])
        ->assertOk()
        ->assertJsonPath('target_user_id', (string) $target->id)
        ->assertJsonPath('status', JamSessionAttendance::STATUS_NOT_GOING);

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $target->id)
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance->source)->toBe(JamSessionAttendance::SOURCE_ADMIN_OVERRIDE);
    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_NOT_GOING);
});

test('admin not going override can release target user slots', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $session = makeAttendanceSession();
    $set = makeAttendanceSet($admin, $session);
    $slot = makeAttendanceSlot($set, 'drums');

    $slot->update(['user_id' => $target->id]);

    $this->actingAs($admin)
        ->postJson(route('sessions.attendance.update', $session), [
            'user_id' => $target->id,
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
            'dropout_action' => JamSessionAttendanceService::DROPOUT_RELEASE_SLOTS,
        ])
        ->assertOk()
        ->assertJsonPath('target_user_id', (string) $target->id)
        ->assertJsonPath('status', JamSessionAttendance::STATUS_NOT_GOING);

    expect($slot->fresh()->user_id)->toBeNull();
});

test('non admin cannot override another users attendance', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $session = makeAttendanceSession();

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'user_id' => $target->id,
            'status' => JamSessionAttendance::STATUS_GOING,
        ])
        ->assertForbidden();
});

test('attendance update requires dropout action when switching to not going with existing sets', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession();

    makeAttendanceSet($user, $session);

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.dropout_action.0', 'Choose how to handle your current slots before marking not going.');

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
            'dropout_action' => JamSessionAttendanceService::DROPOUT_KEEP_CLAIMABLE,
        ])
        ->assertOk()
        ->assertJsonPath('status', JamSessionAttendance::STATUS_NOT_GOING);
});

test('not going blocks creating sets', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession();

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('sets.store', $session), [
            'name' => 'Blocked Set',
            'description' => null,
            'is_hidden' => false,
            'free_for_all' => false,
        ])
        ->assertRedirect();

    expect(Set::query()->where('name', 'Blocked Set')->exists())->toBeFalse();
});

test('creating a set auto marks attendance as going', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession();

    $this->actingAs($user)
        ->post(route('sets.store', $session), [
            'name' => 'Auto Going Set',
            'description' => null,
            'is_hidden' => false,
            'free_for_all' => false,
        ])
        ->assertRedirect();

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $user->id)
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_GOING);
    expect($attendance->source)->toBe(JamSessionAttendance::SOURCE_AUTO_SET);
});

test('not going blocks slot take and slot request', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $session = makeAttendanceSession();
    $set = makeAttendanceSet($owner, $session, [
        'free_for_all' => true,
    ]);
    $slot = makeAttendanceSlot($set);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('slots.take', $slot))
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('slot-assignments.request', $slot), [])
        ->assertStatus(422);

    expect($slot->fresh()->user_id)->toBeNull();
    expect(SlotAssignment::query()->count())->toBe(0);
});

test('admin assignment resets not going target to maybe', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $session = makeAttendanceSession();
    $set = makeAttendanceSet($admin, $session);
    $slot = makeAttendanceSlot($set, 'bass');

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $target->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patchJson(route('slots.update', $slot), [
            'name' => 'bass',
            'notes' => null,
            'user_id' => $target->id,
            'manual_performer_name' => '',
        ])
        ->assertOk();

    expect($slot->fresh()->user_id)->toBe($target->id);

    $attendance = JamSessionAttendance::query()
        ->where('jam_session_id', $session->id)
        ->where('user_id', $target->id)
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe(JamSessionAttendance::STATUS_MAYBE);
    expect($attendance->source)->toBe(JamSessionAttendance::SOURCE_ADMIN_ASSIGNMENT);
});

test('switching to not going with release action releases assigned slots and rejects pending assignments', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $session = makeAttendanceSession();
    $set = makeAttendanceSet($owner, $session);
    $slot = makeAttendanceSlot($set, 'drums');

    $slot->update(['user_id' => $user->id]);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
            'dropout_action' => JamSessionAttendanceService::DROPOUT_RELEASE_SLOTS,
        ])
        ->assertOk();

    expect($slot->fresh()->user_id)->toBeNull();
    expect(SlotAssignment::query()->first()?->status)->toBe(SlotAssignment::STATUS_REJECTED);
});

test('self dropout sends one consolidated notification per impacted recipient with claimable messaging', function () {
    $ownerAdmin = User::factory()->create(['is_admin' => true]);
    $collaborator = User::factory()->create();
    $fellowPerformer = User::factory()->create();
    $dropoutUser = User::factory()->create(['name' => 'Taylor Dropout']);

    $session = makeAttendanceSession(['name' => 'Midweek Jam']);
    $set = makeAttendanceSet($ownerAdmin, $session, [
        'name' => 'Impact Set',
        'collaborator_ids' => [$collaborator->id],
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'The Comets',
        'title' => 'Orbit',
        'notes' => null,
        'position' => 1,
    ]);

    $dropoutSlot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'drums',
        'notes' => null,
        'position' => 1,
        'user_id' => $dropoutUser->id,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'notes' => null,
        'position' => 2,
        'user_id' => $fellowPerformer->id,
    ]);

    $this->actingAs($dropoutUser)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
            'dropout_action' => JamSessionAttendanceService::DROPOUT_KEEP_CLAIMABLE,
        ])
        ->assertOk();

    expect($dropoutSlot->fresh()->user_id)->toBe($dropoutUser->id);

    $ownerNotification = $ownerAdmin->notifications()
        ->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)
        ->latest()
        ->first();

    expect($ownerAdmin->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(1);
    expect($collaborator->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(1);
    expect($fellowPerformer->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(1);
    expect($dropoutUser->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(0);

    expect((string) ($ownerNotification?->data['body'] ?? ''))->toContain('remain assigned but are now claimable');
    expect((string) ($ownerNotification?->data['body'] ?? ''))->toContain('Impact Set');
    expect((string) ($ownerNotification?->data['body'] ?? ''))->toContain('The Comets - Orbit (Drums)');
});

test('self dropout release action includes admin recipients and release messaging', function () {
    $owner = User::factory()->create();
    $unrelatedAdmin = User::factory()->create(['is_admin' => true]);
    $dropoutUser = User::factory()->create(['name' => 'Jordan Dropout']);

    $session = makeAttendanceSession(['name' => 'Saturday Jam']);
    $set = makeAttendanceSet($owner, $session, ['name' => 'Release Set']);
    $slot = makeAttendanceSlot($set, 'vocals');
    $slot->update(['user_id' => $dropoutUser->id]);

    $this->actingAs($dropoutUser)
        ->postJson(route('sessions.attendance.update', $session), [
            'status' => JamSessionAttendance::STATUS_NOT_GOING,
            'dropout_action' => JamSessionAttendanceService::DROPOUT_RELEASE_SLOTS,
        ])
        ->assertOk();

    expect($slot->fresh()->user_id)->toBeNull();

    $adminNotification = $unrelatedAdmin->notifications()
        ->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)
        ->latest()
        ->first();

    expect($owner->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(1);
    expect($unrelatedAdmin->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_DROPPED_FROM_SET)->count())->toBe(1);
    expect((string) ($adminNotification?->data['body'] ?? ''))->toContain('They chose to release their 1 slot.');
});

test('slot suggestion UIs include not attending section heading', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $session = makeAttendanceSession();
    $set = makeAttendanceSet($owner, $session);
    $slot = makeAttendanceSlot($set);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee('Not attending')
        ->assertSee('attendance_group', false);
});

test('jam register sign-in promotes not going attendance to going', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession([
        'allow_checkins' => true,
        'is_hidden' => false,
    ]);

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

test('past sessions render attendance controls in history mode', function () {
    $user = User::factory()->create();
    $session = makeAttendanceSession([
        'date' => now()->subDay()->toDateString(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $user->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('isPastSession: true', false)
        ->assertSee("shouldShowStatusButton('not_going')", false)
        ->assertSee('hasVisibleStatusButtons()', false)
        ->assertSee("statusButtonLabel('going')", false)
        ->assertSee('modalStatusLabel(user.status)', false)
        ->assertSee('canManageAttendanceForUser(user)', false);
});
