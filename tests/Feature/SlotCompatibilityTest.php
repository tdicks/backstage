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

function createSlotCompatibilitySlot(Set $set, string $slotName, ?User $user = null, int $position = 1): Slot
{
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Test Artist '.$position,
        'title' => 'Test Song '.$position,
        'notes' => null,
        'position' => $position,
    ]);

    return Slot::create([
        'song_id' => $song->id,
        'name' => $slotName,
        'position' => 1,
        'user_id' => $user?->id,
    ]);
}

test('player cannot take bass and guitar slots in the same set', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $owner, 1);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, 2);

    $this->actingAs($owner)
        ->post(route('slots.take', $guitarSlot))
        ->assertSessionHasErrors('user_id');

    expect(session('errors')->get('user_id')[0])->toContain("don't have enough limbs");

    expect($guitarSlot->refresh()->user_id)->toBeNull();
});

test('ajax take slot conflict returns json for toast notification', function () {
    $owner = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $owner, 1);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, 2);

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

test('set owner cannot manually assign a player to incompatible slot types', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'bass', $player, 1);
    $guitarSlot = createSlotCompatibilitySlot($set, 'lead_guitar', null, 2);

    $this->actingAs($owner)
        ->patch(route('slots.update', $guitarSlot), [
            'name' => 'lead_guitar',
            'user_id' => $player->id,
            'manual_performer_name' => null,
            'position' => $guitarSlot->position,
        ])
        ->assertSessionHasErrors('user_id');

    expect($guitarSlot->refresh()->user_id)->toBeNull();
});

test('set owner cannot approve a request that conflicts with an existing set slot', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create();
    $set = createSlotCompatibilitySet($owner);

    createSlotCompatibilitySlot($set, 'keys', $player, 1);
    $drumsSlot = createSlotCompatibilitySlot($set, 'drums', null, 2);

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
        ->assertSessionHasErrors('user_id');

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_PENDING);
    expect($drumsSlot->refresh()->user_id)->toBeNull();
});
