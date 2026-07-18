<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can reorder songs', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Reorder Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Reorder Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $songOne = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist One',
        'title' => 'Song One',
        'notes' => null,
        'position' => 1,
    ]);

    $songTwo = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist Two',
        'title' => 'Song Two',
        'notes' => null,
        'position' => 2,
    ]);

    $songThree = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist Three',
        'title' => 'Song Three',
        'notes' => null,
        'position' => 3,
    ]);

    $this->actingAs($owner)
        ->patch(route('songs.reorder', $set), [
            'song_ids' => [$songThree->id, $songOne->id, $songTwo->id],
        ])
        ->assertRedirect();

    expect($songThree->refresh()->position)->toBe(1);
    expect($songOne->refresh()->position)->toBe(2);
    expect($songTwo->refresh()->position)->toBe(3);
});

test('set owner can reorder songs with json payload', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Reorder JSON Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Reorder JSON Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $songOne = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist One',
        'title' => 'Song One',
        'notes' => null,
        'position' => 1,
    ]);

    $songTwo = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist Two',
        'title' => 'Song Two',
        'notes' => null,
        'position' => 2,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('songs.reorder', $set), [
            'song_ids' => [$songTwo->id, $songOne->id],
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Song order updated.',
        ]);

    expect($songTwo->refresh()->position)->toBe(1);
    expect($songOne->refresh()->position)->toBe(2);
});

test('non-owner cannot reorder songs in set', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Reorder Permission Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Permission Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $songOne = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist One',
        'title' => 'Song One',
        'notes' => null,
        'position' => 1,
    ]);

    $songTwo = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist Two',
        'title' => 'Song Two',
        'notes' => null,
        'position' => 2,
    ]);

    $this->actingAs($other)
        ->patch(route('songs.reorder', $set), [
            'song_ids' => [$songTwo->id, $songOne->id],
        ])
        ->assertForbidden();

    expect($songOne->refresh()->position)->toBe(1);
    expect($songTwo->refresh()->position)->toBe(2);
});

test('performed sets cannot be reordered', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Performed Reorder Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Performed Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => true,
        'signups_open' => false,
    ]);

    $songOne = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist One',
        'title' => 'Song One',
        'notes' => null,
        'position' => 1,
    ]);

    $songTwo = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist Two',
        'title' => 'Song Two',
        'notes' => null,
        'position' => 2,
    ]);

    $this->actingAs($owner)
        ->patch(route('songs.reorder', $set), [
            'song_ids' => [$songTwo->id, $songOne->id],
        ])
        ->assertForbidden();

    expect($songOne->refresh()->position)->toBe(1);
    expect($songTwo->refresh()->position)->toBe(2);
});
