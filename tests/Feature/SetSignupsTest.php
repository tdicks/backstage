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