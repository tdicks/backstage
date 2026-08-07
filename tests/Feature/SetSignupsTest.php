<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can close and reopen signups', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Toggle Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Toggle Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Toggle Set',
            'description' => null,
            'performed' => 0,
            'signups_open' => 0,
        ])
        ->assertRedirect();

    expect($set->refresh()->signups_open)->toBeFalse();

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Toggle Set',
            'description' => null,
            'performed' => 0,
            'signups_open' => 1,
        ])
        ->assertRedirect();

    expect($set->refresh()->signups_open)->toBeTrue();
});

test('non owner cannot change signups state', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Auth Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Auth Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($other)
        ->patch(route('sets.update', $set), [
            'name' => 'Auth Set',
            'description' => null,
            'performed' => 0,
            'signups_open' => 0,
        ])
        ->assertForbidden();

    expect($set->refresh()->signups_open)->toBeTrue();
});

test('closed signups block new slot requests', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Closed Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => false,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Rush',
        'title' => 'Tom Sawyer',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->post(route('slot-assignments.request', $slot))
        ->assertRedirect();

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('requester can cancel their slot request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Cancel Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Cancel Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Nirvana',
        'title' => 'Come As You Are',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->postJson(route('slot-assignments.request', $slot), [])
        ->assertCreated();

    $assignment = SlotAssignment::query()->firstOrFail();

    $this->actingAs($requester)
        ->patchJson(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_REJECTED,
        ])
        ->assertOk();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_REJECTED);
});

test('non-admin cannot propose a slot recommendation on a closed session', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Recommendation Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Closed Recommendation Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Forty Six & 2',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    $this->actingAs($actor)
        ->postJson(route('slot-assignments.propose', $slot), [
            'target_user_id' => $target->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This jam session is closed.');

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('non-admin cannot request a slot on a closed session', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Request Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Closed Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Pneuma',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->postJson(route('slot-assignments.request', $slot), [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This jam session is closed.');

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('non-admin cannot take a slot on a closed session even in free for all mode', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Take Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Closed Take Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
        'free_for_all' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Lateralus',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->postJson(route('slots.take', $slot), [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This jam session is closed.');

    expect($slot->fresh()->user_id)->toBeNull();
});

test('non-admin cannot release a slot on a closed session', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Release Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Closed Release Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Schism',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => $requester->id,
    ]);

    $this->actingAs($requester)
        ->postJson(route('slots.release', $slot), [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This jam session is closed.');

    expect($slot->fresh()->user_id)->toBe($requester->id);
});

test('cannot propose a slot recommendation on a performed set', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Performed Recommendation Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::create([
        'name' => 'Performed Recommendation Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => true,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Invincible',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($actor)
        ->postJson(route('slot-assignments.propose', $slot), [
            'target_user_id' => $target->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This set has already been performed.');

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('cannot propose slots to users who hide from proposals', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $hiddenTarget = User::factory()->create([
        'hide_from_slot_proposals' => true,
    ]);

    $session = JamSession::create([
        'name' => 'Proposal Privacy Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Proposal Privacy Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Schism',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    $this->actingAs($actor)
        ->post(route('slot-assignments.propose', $slot), [
            'target_user_id' => $hiddenTarget->id,
            'message' => 'You should play this.',
        ])
        ->assertSessionHasErrors('target_user_id');

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('cannot propose slots to deleted users', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $deletedTarget = User::factory()->create([
        'is_deleted_account' => true,
        'deleted_account_at' => now(),
        'hide_from_slot_proposals' => false,
    ]);

    $session = JamSession::create([
        'name' => 'Proposal Deleted User Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Proposal Deleted User Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Tool',
        'title' => 'Lateralus',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    $this->actingAs($actor)
        ->post(route('slot-assignments.propose', $slot), [
            'target_user_id' => $deletedTarget->id,
            'message' => 'You should play this.',
        ])
        ->assertSessionHasErrors('target_user_id');

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('admin can change set owner from set update endpoint', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Change Session',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Change Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('sets.update', $set), [
            'name' => 'Owner Change Set',
            'description' => null,
            'performed' => 0,
            'owner_id' => $newOwner->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->owner_id)->toBe($newOwner->id);
});

test('non-admin cannot change set owner via set update endpoint', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Guard Session',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Guard Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Owner Guard Set',
            'description' => null,
            'performed' => 0,
            'owner_id' => $other->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->owner_id)->toBe($owner->id);
});

test('set owner can change which jam session the set belongs to', function () {
    $owner = User::factory()->create();

    $originalSession = JamSession::create([
        'name' => 'Original Session',
        'date' => now()->addDays(6),
        'description' => null,
    ]);

    $targetSession = JamSession::create([
        'name' => 'Target Session',
        'date' => now()->addDays(7),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Movable Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $originalSession->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Movable Set',
            'description' => null,
            'performed' => 0,
            'jam_session_id' => $targetSession->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->jam_session_id)->toBe($targetSession->id);
});

test('set owner cannot move a set to a closed jam session', function () {
    $owner = User::factory()->create();

    $originalSession = JamSession::create([
        'name' => 'Open Source Session',
        'date' => now()->addDays(6),
        'description' => null,
        'is_closed' => false,
    ]);

    $closedTargetSession = JamSession::create([
        'name' => 'Closed Target Session',
        'date' => now()->addDays(7),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Locked Destination Guard Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $originalSession->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->from(route('sessions.show', $originalSession))
        ->patch(route('sets.update', $set), [
            'name' => 'Locked Destination Guard Set',
            'description' => null,
            'performed' => 0,
            'jam_session_id' => $closedTargetSession->id,
        ])
        ->assertRedirect(route('sessions.show', $originalSession))
        ->assertSessionHasErrors('jam_session_id');

    expect($set->refresh()->jam_session_id)->toBe($originalSession->id);
});

test('set owner cannot move a set to a past jam session', function () {
    $owner = User::factory()->create();

    $originalSession = JamSession::create([
        'name' => 'Current Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
    ]);

    $pastTargetSession = JamSession::create([
        'name' => 'Past Session',
        'date' => now()->subDay(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::create([
        'name' => 'Past Destination Guard Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $originalSession->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->from(route('sessions.show', $originalSession))
        ->patch(route('sets.update', $set), [
            'name' => 'Past Destination Guard Set',
            'description' => null,
            'performed' => 0,
            'jam_session_id' => $pastTargetSession->id,
        ])
        ->assertRedirect(route('sessions.show', $originalSession))
        ->assertSessionHasErrors('jam_session_id');

    expect($set->refresh()->jam_session_id)->toBe($originalSession->id);
});

test('admin can move a set to closed and past jam sessions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $originalSession = JamSession::create([
        'name' => 'Admin Source Session',
        'date' => now()->addDays(4),
        'description' => null,
        'is_closed' => false,
    ]);

    $closedPastSession = JamSession::create([
        'name' => 'Admin Closed Past Session',
        'date' => now()->subDays(2),
        'description' => null,
        'is_closed' => true,
    ]);

    $set = Set::create([
        'name' => 'Admin Movable Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $originalSession->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('sets.update', $set), [
            'name' => 'Admin Movable Set',
            'description' => null,
            'performed' => 0,
            'jam_session_id' => $closedPastSession->id,
            'owner_id' => $owner->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->jam_session_id)->toBe($closedPastSession->id);
});
