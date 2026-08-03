<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can view and restore their deleted set from recycle bin', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Restore Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Restore Me',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->assertSoftDeleted('sets', [
        'id' => $set->id,
        'deleted_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('sets.0.id', $set->id);

    $this->actingAs($owner)
        ->patchJson(route('recycle-bin.sets.restore', $set->id))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->assertDatabaseHas('sets', [
        'id' => $set->id,
        'deleted_at' => null,
    ]);
});

test('set can be restored as hidden', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Hidden Restore Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Restore Hidden Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'is_hidden' => false,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($owner)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'restore_as_hidden' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('sets', [
        'id' => $set->id,
        'deleted_at' => null,
        'is_hidden' => true,
    ]);
});

test('set can be restored while clearing all slot assignments', function () {
    $owner = User::factory()->create();
    $performer = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Clear Slots Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Restore And Clear',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $song = Song::query()->create([
        'artist' => 'Artist',
        'title' => 'Song',
        'set_id' => $set->id,
        'position' => 1,
    ]);

    $assignedSlot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => $performer->id,
        'position' => 1,
        'is_claimable_manual' => true,
    ]);

    $manualSlot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'drums',
        'manual_performer_name' => 'Temp Player',
        'position' => 2,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($owner)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'clear_slot_assignments' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('sets', [
        'id' => $set->id,
        'deleted_at' => null,
    ]);

    $this->assertDatabaseHas('slots', [
        'id' => $assignedSlot->id,
        'user_id' => null,
        'manual_performer_name' => null,
        'is_claimable_manual' => false,
    ]);

    $this->assertDatabaseHas('slots', [
        'id' => $manualSlot->id,
        'user_id' => null,
        'manual_performer_name' => null,
        'is_claimable_manual' => false,
    ]);
});

test('recycle bin items response includes overview data for deleted sessions and sets', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['name' => 'Set Owner']);
    $performer = User::factory()->create(['name' => 'Lead Singer']);

    $session = JamSession::query()->create([
        'name' => 'Overview Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $deletedSessionSet = Set::query()->create([
        'name' => 'Deleted Session Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $deletedSessionSong = Song::query()->create([
        'artist' => 'The Band',
        'title' => 'Session Song',
        'set_id' => $deletedSessionSet->id,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $deletedSessionSong->id,
        'name' => 'vocals',
        'user_id' => $performer->id,
        'position' => 1,
    ]);

    $liveSession = JamSession::query()->create([
        'name' => 'Live Session',
        'date' => now()->addDays(10)->toDateString(),
    ]);

    $deletedSet = Set::query()->create([
        'name' => 'Detailed Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $liveSession->id,
        'position' => 1,
        'is_hidden' => true,
        'free_for_all' => true,
        'signups_open' => false,
        'song_requests' => true,
        'feature_set' => true,
    ]);

    $deletedSetSong = Song::query()->create([
        'artist' => 'Another Band',
        'title' => 'Detailed Song',
        'set_id' => $deletedSet->id,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $deletedSetSong->id,
        'name' => 'drums',
        'manual_performer_name' => 'Guest Drummer',
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $deletedSet))
        ->assertRedirect();

    $response = $this->actingAs($admin)
        ->getJson(route('recycle-bin.items'))
        ->assertOk();

    $sessionOverview = collect($response->json('sessions.0.overview.sets'))->firstWhere('name', 'Deleted Session Set');
    $detailedSetPayload = collect($response->json('sets'))->firstWhere('name', 'Detailed Set');
    $deletedSessionSetPayload = collect($response->json('sets'))->firstWhere('name', 'Deleted Session Set');

    expect($sessionOverview)->not->toBeNull();
    expect(data_get($sessionOverview, 'songs.0.title'))->toBe('Session Song');
    expect($deletedSessionSetPayload)->not->toBeNull();
    expect(data_get($deletedSessionSetPayload, 'session_deleted'))->toBeTrue();
    expect($detailedSetPayload)->not->toBeNull();
    expect(data_get($detailedSetPayload, 'overview.settings.0.label'))->toBe('Hidden');
    expect(data_get($detailedSetPayload, 'overview.settings.0.enabled'))->toBeTrue();
    expect(data_get($detailedSetPayload, 'overview.songs.0.title'))->toBe('Detailed Song');
    expect(data_get($detailedSetPayload, 'overview.songs.0.slots.0.label'))->toBe('Drums');
    expect(data_get($detailedSetPayload, 'overview.songs.0.slots.0.performer_name'))->toBe('Guest Drummer');
});

test('recycle bin items response includes restore destination session options for non admins', function () {
    $owner = User::factory()->create();

    $visibleOpenSession = JamSession::query()->create([
        'name' => 'Visible Open Session',
        'date' => now()->addDays(5)->toDateString(),
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $visibleClosedSession = JamSession::query()->create([
        'name' => 'Visible Closed Session',
        'date' => now()->addDays(6)->toDateString(),
        'is_closed' => true,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $pastSession = JamSession::query()->create([
        'name' => 'Past Session',
        'date' => now()->subDay()->toDateString(),
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $hiddenSession = JamSession::query()->create([
        'name' => 'Hidden Session',
        'date' => now()->addDays(7)->toDateString(),
        'is_closed' => false,
        'is_hidden' => true,
        'is_archived' => false,
    ]);

    $archivedSession = JamSession::query()->create([
        'name' => 'Archived Session',
        'date' => now()->addDays(8)->toDateString(),
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => true,
    ]);

    $sourceSet = Set::query()->create([
        'name' => 'Restore Options Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $visibleOpenSession->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $sourceSet))
        ->assertRedirect();

    $this->actingAs($owner)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $visibleOpenSession->id,
            'label' => 'Visible Open Session ('.$visibleOpenSession->date->format('M j, Y').')',
            'disabled' => false,
        ])
        ->assertJsonFragment([
            'id' => $visibleClosedSession->id,
            'label' => 'Visible Closed Session ('.$visibleClosedSession->date->format('M j, Y').') (Closed)',
            'disabled' => true,
        ])
        ->assertJsonMissing(['id' => $pastSession->id])
        ->assertJsonMissing(['id' => $hiddenSession->id])
        ->assertJsonMissing(['id' => $archivedSession->id]);
});

test('admin restore destination session options include closed and past sessions but exclude archived sessions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $openSession = JamSession::query()->create([
        'name' => 'Admin Open Session',
        'date' => now()->addDays(5)->toDateString(),
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $closedPastSession = JamSession::query()->create([
        'name' => 'Admin Closed Past Session',
        'date' => now()->subDays(2)->toDateString(),
        'is_closed' => true,
        'is_archived' => false,
    ]);

    $archivedSession = JamSession::query()->create([
        'name' => 'Admin Archived Session',
        'date' => now()->addDays(6)->toDateString(),
        'is_closed' => false,
        'is_archived' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Restore Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $openSession->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($admin)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $closedPastSession->id,
            'label' => 'Admin Closed Past Session ('.$closedPastSession->date->format('M j, Y').') (Closed)',
            'disabled' => false,
        ])
        ->assertJsonMissing(['id' => $archivedSession->id]);
});

test('user cannot restore another users deleted set', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Ownership Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Private Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($otherUser)
        ->patchJson(route('recycle-bin.sets.restore', $set->id))
        ->assertForbidden();

    $this->assertSoftDeleted('sets', ['id' => $set->id]);
});

test('set owner cannot see or restore their set when it was deleted by an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Admin Moderation Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Moderated Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->assertSoftDeleted('sets', [
        'id' => $set->id,
        'deleted_by_user_id' => $admin->id,
    ]);

    $this->actingAs($owner)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonCount(0, 'sets');

    $this->actingAs($owner)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'jam_session_id' => $session->id,
        ])
        ->assertForbidden();
});

test('admin can view deleted jam sessions with deleted sets and restoring session with no selected sets keeps sets deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Deleted Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Set In Session',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $this->assertSoftDeleted('jam_sessions', ['id' => $session->id]);
    $this->assertSoftDeleted('sets', ['id' => $set->id]);

    $this->actingAs($admin)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('sessions.0.id', $session->id)
        ->assertJsonPath('sets.0.id', $set->id)
        ->assertJsonPath('sessions.0.deleted_sets.0.id', $set->id);

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sessions.restore', $session->id), [
            'restore_as_hidden' => true,
            'selected_set_ids' => [],
        ])
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->assertDatabaseHas('jam_sessions', [
        'id' => $session->id,
        'deleted_at' => null,
        'is_hidden' => true,
    ]);

    $this->assertSoftDeleted('sets', [
        'id' => $set->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('sets.0.id', $set->id)
        ->assertJsonCount(0, 'sessions');
});

test('restoring a deleted jam session restores all deleted sets by default', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Restore All Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $firstSet = Set::query()->create([
        'name' => 'First Deleted Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $secondSet = Set::query()->create([
        'name' => 'Second Deleted Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sessions.restore', $session->id), [
            'restore_as_hidden' => false,
        ])
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->assertDatabaseHas('jam_sessions', [
        'id' => $session->id,
        'deleted_at' => null,
    ]);

    $this->assertDatabaseHas('sets', [
        'id' => $firstSet->id,
        'deleted_at' => null,
    ]);

    $this->assertDatabaseHas('sets', [
        'id' => $secondSet->id,
        'deleted_at' => null,
    ]);
});

test('restoring a deleted jam session can restore only selected deleted sets', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Selective Restore Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $selectedSet = Set::query()->create([
        'name' => 'Selected Deleted Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $remainingSet = Set::query()->create([
        'name' => 'Remaining Deleted Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sessions.restore', $session->id), [
            'selected_set_ids' => [$selectedSet->id],
        ])
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->assertDatabaseHas('sets', [
        'id' => $selectedSet->id,
        'deleted_at' => null,
    ]);

    $this->assertSoftDeleted('sets', [
        'id' => $remainingSet->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonCount(0, 'sessions')
        ->assertJsonPath('sets.0.id', $remainingSet->id);
});

test('admin can restore a deleted set to a different jam session when its original jam session is deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $deletedSession = JamSession::query()->create([
        'name' => 'Deleted Source Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $targetSession = JamSession::query()->create([
        'name' => 'Restore Target Session',
        'date' => now()->addWeeks(2)->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Move On Restore Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $deletedSession->id,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $deletedSession))
        ->assertRedirect(route('sessions.index'));

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'jam_session_id' => $targetSession->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('sets', [
        'id' => $set->id,
        'deleted_at' => null,
        'jam_session_id' => $targetSession->id,
    ]);
});

test('set owner cannot restore a deleted set to a closed jam session', function () {
    $owner = User::factory()->create();

    $sourceSession = JamSession::query()->create([
        'name' => 'Restore Source Session',
        'date' => now()->addWeek()->toDateString(),
        'is_closed' => false,
    ]);

    $closedSession = JamSession::query()->create([
        'name' => 'Closed Restore Target Session',
        'date' => now()->addWeeks(2)->toDateString(),
        'is_closed' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Closed Restore Guard Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $sourceSession->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($owner)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'jam_session_id' => $closedSession->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sets can only be restored to open jam sessions.');

    $this->assertSoftDeleted('sets', ['id' => $set->id]);
});

test('admin can restore a deleted set to a closed past jam session but not an archived session', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $sourceSession = JamSession::query()->create([
        'name' => 'Admin Restore Source Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $closedPastSession = JamSession::query()->create([
        'name' => 'Admin Closed Past Restore Session',
        'date' => now()->subDays(3)->toDateString(),
        'is_closed' => true,
        'is_archived' => false,
    ]);

    $archivedSession = JamSession::query()->create([
        'name' => 'Admin Archived Restore Session',
        'date' => now()->addDays(6)->toDateString(),
        'is_archived' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Restore Destination Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $sourceSession->id,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'jam_session_id' => $closedPastSession->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('sets', [
        'id' => $set->id,
        'deleted_at' => null,
        'jam_session_id' => $closedPastSession->id,
    ]);

    $set->delete();

    $this->actingAs($admin)
        ->patchJson(route('recycle-bin.sets.restore', $set->id), [
            'jam_session_id' => $archivedSession->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Choose a valid jam session to restore this set to.');
});

test('non admin recycle bin listing excludes deleted jam sessions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Admin Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $this->actingAs($user)
        ->getJson(route('recycle-bin.items'))
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonCount(0, 'sessions')
        ->assertJsonCount(0, 'sets');

    $this->assertSoftDeleted('jam_sessions', ['id' => $session->id]);
    $this->assertSoftDeleted('sets', ['id' => $set->id]);
});
