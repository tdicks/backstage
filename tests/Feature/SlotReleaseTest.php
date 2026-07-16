<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('assigned user can release a slot', function () {
    $assignedUser = User::factory()->create();
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Release Test Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Release Test Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
    ]);

    $song = Song::create([
        'artist' => 'Nirvana',
        'title' => 'In Bloom',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => $assignedUser->id,
    ]);

    $this->actingAs($assignedUser)
        ->post(route('slots.release', $slot))
        ->assertRedirect();

    expect($slot->refresh()->user_id)->toBeNull();
});

test('non-assigned user cannot release a slot', function () {
    $assignedUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Release Auth Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Release Auth Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
    ]);

    $song = Song::create([
        'artist' => 'Radiohead',
        'title' => 'Creep',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => $assignedUser->id,
    ]);

    $this->actingAs($otherUser)
        ->post(route('slots.release', $slot))
        ->assertForbidden();

    expect($slot->refresh()->user_id)->toBe($assignedUser->id);
});