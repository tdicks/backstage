<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can reorder slots within a song', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Slot Reorder Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Slot Reorder Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist One',
        'title' => 'Song One',
        'notes' => null,
        'position' => 1,
    ]);

    $slotOne = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => null,
        'position' => 1,
    ]);

    $slotTwo = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
        'position' => 2,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('slots.reorder', $song), [
            'slot_ids' => [$slotTwo->id, $slotOne->id],
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Slot order updated.',
        ]);

    expect($slotTwo->refresh()->position)->toBe(1);
    expect($slotOne->refresh()->position)->toBe(2);
});