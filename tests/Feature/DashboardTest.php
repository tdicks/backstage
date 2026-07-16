<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard shows the signed up sets, songs, and slots', function () {
    $user = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Friday Night Jam',
        'date' => now()->addWeek(),
        'description' => 'A loud one.',
    ]);

    $set = Set::create([
        'name' => 'Opening Set',
        'description' => 'First block of the night.',
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'performed' => true,
    ]);

    $song = Song::create([
        'artist' => 'Black Sabbath',
        'title' => 'Paranoid',
        'notes' => 'Fast tempo.',
        'set_id' => $set->id,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Signups')
        ->assertSee('Opening Set')
        ->assertSee('Black Sabbath - Paranoid')
        ->assertSee('Vocals')
        ->assertSee('Performed')
        ->assertSee('bg-gray-100', false);
});

test('dashboard shows pending slot proposals for the current user', function () {
    $targetUser = User::factory()->create(['name' => 'Target User']);
    $actorUser = User::factory()->create(['name' => 'Actor User']);
    $otherUser = User::factory()->create(['name' => 'Other User']);

    $session = JamSession::create([
        'name' => 'Sunday Jam',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Proposal Set',
        'description' => null,
        'owner_id' => $actorUser->id,
        'jam_session_id' => $session->id,
        'performed' => false,
    ]);

    $song = Song::create([
        'artist' => 'Muse',
        'title' => 'Hysteria',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actorUser->id,
        'target_user_id' => $targetUser->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'You would crush this one.',
    ]);

    SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actorUser->id,
        'target_user_id' => $otherUser->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'Not for the target user.',
    ]);

    $this->actingAs($targetUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Slot proposals for you')
        ->assertSee('Muse - Hysteria')
        ->assertSee('Slot: Bass')
        ->assertSee('Proposed by Actor User')
        ->assertSee('You would crush this one.')
        ->assertDontSee('Not for the target user.');
});