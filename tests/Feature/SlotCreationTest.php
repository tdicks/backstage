<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can add a keys slot to a song', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Keys Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Keys Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Doors',
        'title' => 'Light My Fire',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->post(route('slots.store', $song), [
            'name' => 'keys',
            'user_id' => null,
        ])
        ->assertRedirect();

    expect(Slot::query()
        ->where('song_id', $song->id)
        ->where('name', 'keys')
        ->exists())
        ->toBeTrue();
});