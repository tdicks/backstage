<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSlotCompatibilitySet(User $owner): Set
{
    $session = JamSession::create([
        'name' => 'Compatibility Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    return Set::create([
        'name' => 'Compatibility Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);
}

function createSongForSet(Set $set, int $position = 1): Song
{
    return Song::create([
        'set_id' => $set->id,
        'artist' => 'Test Artist '.$position,
        'title' => 'Test Song '.$position,
        'notes' => null,
        'position' => $position,
    ]);
}

function createSlotCompatibilitySlot(Set $set, string $slotName, ?User $user = null, int $position = 1, ?Song $song = null): Slot
{
    $song ??= createSongForSet($set, $position);

    return Slot::create([
        'song_id' => $song->id,
        'name' => $slotName,
        'position' => 1,
        'user_id' => $user?->id,
    ]);
}

test('player cannot take bass and guitar slots on the same song', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);

    createSlotCompatibilitySlot($set, 'bass', $owner, song: $song);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, song: $song);

    $this->actingAs($owner)
        ->post(route('slots.take', $guitarSlot))
        ->assertSessionHasErrors('user_id');

    expect(session('errors')->get('user_id')[0])->toContain("don't have enough limbs");

    expect($guitarSlot->refresh()->user_id)->toBeNull();
});

test('ajax take slot conflict returns json for toast notification', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);

    createSlotCompatibilitySlot($set, 'bass', $owner, song: $song);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, song: $song);

    $response = $this->actingAs($owner)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('slots.take', $guitarSlot));

    expect($response->getStatusCode())->toBe(422);
    expect($response->json('message'))->toContain("don't have enough limbs");
    expect($response->json('errors.user_id.0'))->toContain("don't have enough limbs");
    expect($guitarSlot->refresh()->user_id)->toBeNull();
});

test('player can take the same instrument on multiple songs in the same set', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'drums', $owner, 1);
    $secondDrumsSlot = createSlotCompatibilitySlot($set, 'drums', null, 2);

    $this->actingAs($owner)
        ->post(route('slots.take', $secondDrumsSlot))
        ->assertRedirect();

    expect($secondDrumsSlot->refresh()->user_id)->toBe($owner->id);
});

test('player can take conflicting slot types on different songs in the same set', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $owner, 1);
    $guitarSlotOnDifferentSong = createSlotCompatibilitySlot($set, 'lead_guitar', null, 2);

    $this->actingAs($owner)
        ->post(route('slots.take', $guitarSlotOnDifferentSong))
        ->assertRedirect();

    expect($guitarSlotOnDifferentSong->refresh()->user_id)->toBe($owner->id);
});

test('vocals do not conflict with instrument slots', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $owner, 1);
    $vocalsSlot = createSlotCompatibilitySlot($set, 'vocals', null, 2);

    $this->actingAs($owner)
        ->post(route('slots.take', $vocalsSlot))
        ->assertRedirect();

    expect($vocalsSlot->refresh()->user_id)->toBe($owner->id);
});

test('set owner is advised before moving a player to an incompatible slot on the same song', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);

    $bassSlot = createSlotCompatibilitySlot($set, 'bass', $player, song: $song);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, song: $song);

    $this->actingAs($owner)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('slots.update', $guitarSlot), [
            'name' => 'lead_guitar',
            'user_id' => $player->id,
            'manual_performer_name' => null,
            'position' => $guitarSlot->position,
        ])
        ->assertConflict()
        ->assertJsonPath('conflict.slot_id', $bassSlot->id)
        ->assertJsonPath('conflict.slot_label', 'Bass')
        ->assertJsonPath('message', $player->name.' is already assigned to Bass on this song. Moving them to Lead Guitar will clear that assignment.');

    expect($bassSlot->refresh()->user_id)->toBe($player->id);
    expect($guitarSlot->refresh()->user_id)->toBeNull();

    $this->actingAs($owner)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('slots.update', $guitarSlot), [
            'name' => 'lead_guitar',
            'user_id' => $player->id,
            'manual_performer_name' => null,
            'position' => $guitarSlot->position,
            'replace_conflicting_assignment' => true,
        ])
        ->assertOk();

    expect($bassSlot->refresh()->user_id)->toBeNull();
    expect($bassSlot->manual_performer_name)->toBeNull();
    expect($guitarSlot->refresh()->user_id)->toBe($player->id);
});

test('set owner can assign a player to conflicting slot types on different songs', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $player, 1);
    $guitarSlotOnDifferentSong = createSlotCompatibilitySlot($set, 'lead_guitar', null, 2);

    $this->actingAs($owner)
        ->patch(route('slots.update', $guitarSlotOnDifferentSong), [
            'name' => 'lead_guitar',
            'user_id' => $player->id,
            'manual_performer_name' => null,
            'position' => $guitarSlotOnDifferentSong->position,
        ])
        ->assertRedirect();

    expect($guitarSlotOnDifferentSong->refresh()->user_id)->toBe($player->id);
});

test('set owner can clear a slot assignment and manual performer name', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);
    $slot = createSlotCompatibilitySlot($set, 'vocals', $player, song: $song);
    $slot->update(['manual_performer_name' => 'Guest Singer']);

    $this->actingAs($owner)
        ->patch(route('slots.update', $slot), [
            'name' => 'vocals',
            'user_id' => null,
            'manual_performer_name' => '',
            'position' => $slot->position,
        ])
        ->assertRedirect();

    expect($slot->refresh()->user_id)->toBeNull();
    expect($slot->manual_performer_name)->toBeNull();
    expect($slot->isOpen())->toBeTrue();
});

test('set owner can approve a request that moves a player from a conflicting slot on the same song', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);

    $keysSlot = createSlotCompatibilitySlot($set, 'keys', $player, song: $song);
    $drumsSlot = createSlotCompatibilitySlot($set, 'drums', null, song: $song);

    $assignment = SlotAssignment::create([
        'slot_id' => $drumsSlot->id,
        'actor_user_id' => $player->id,
        'target_user_id' => $player->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($keysSlot->refresh()->user_id)->toBeNull();
    expect($drumsSlot->refresh()->user_id)->toBe($player->id);
});

test('owner recommendation acceptance moves a player from a conflicting slot on the same song', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);
    $song = createSongForSet($set);

    $keysSlot = createSlotCompatibilitySlot($set, 'keys', $player, song: $song);
    $drumsSlot = createSlotCompatibilitySlot($set, 'drums', null, song: $song);

    $assignment = SlotAssignment::create([
        'slot_id' => $drumsSlot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $player->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    $this->actingAs($player)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($keysSlot->refresh()->user_id)->toBeNull();
    expect($drumsSlot->refresh()->user_id)->toBe($player->id);
});

test('set owner can approve a request for conflicting slot types on different songs', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'keys', $player, 1);
    $drumsSlotOnDifferentSong = createSlotCompatibilitySlot($set, 'drums', null, 2);

    $assignment = SlotAssignment::create([
        'slot_id' => $drumsSlotOnDifferentSong->id,
        'actor_user_id' => $player->id,
        'target_user_id' => $player->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($drumsSlotOnDifferentSong->refresh()->user_id)->toBe($player->id);
});
