<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('my sets page shows owned sets and pending approvals for owner sets', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $ownedSet = Set::create([
        'name' => 'Owned Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $otherSet = Set::create([
        'name' => 'Other Set',
        'description' => null,
        'owner_id' => $other->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
    ]);

    $ownedSong = Song::create([
        'set_id' => $ownedSet->id,
        'artist' => 'Band A',
        'title' => 'Track A',
        'notes' => null,
        'position' => 1,
    ]);

    $otherSong = Song::create([
        'set_id' => $otherSet->id,
        'artist' => 'Band B',
        'title' => 'Track B',
        'notes' => null,
        'position' => 1,
    ]);

    $ownedSlot = Slot::create([
        'song_id' => $ownedSong->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $otherSlot = Slot::create([
        'song_id' => $otherSong->id,
        'name' => 'drums',
        'position' => 1,
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $ownedSlot->id,
        'actor_user_id' => $other->id,
        'target_user_id' => $other->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    SlotAssignment::create([
        'slot_id' => $otherSlot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $owner->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('Owned Set')
        ->assertDontSee('Other Set')
        ->assertSee('Pending slot approvals');
});

test('set owner can accept proposal assignment for their set', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Proposal Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Proposal Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Band X',
        'title' => 'Track X',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $assignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($slot->refresh()->user_id)->toBe($target->id);
});
