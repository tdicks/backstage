<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sets songs and slots are ordered by position', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Position Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $secondSet = Set::create([
        'name' => 'Second Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
    ]);

    $firstSet = Set::create([
        'name' => 'First Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $lateSong = Song::create([
        'set_id' => $firstSet->id,
        'artist' => 'Artist B',
        'title' => 'Song B',
        'notes' => null,
        'position' => 2,
    ]);

    $earlySong = Song::create([
        'set_id' => $firstSet->id,
        'artist' => 'Artist A',
        'title' => 'Song A',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $earlySong->id,
        'name' => 'bass',
        'user_id' => null,
        'position' => 2,
    ]);

    Slot::create([
        'song_id' => $earlySong->id,
        'name' => 'vocals',
        'user_id' => null,
        'position' => 1,
    ]);

    $session->refresh()->load('sets.songs.slots');

    expect($session->sets->pluck('id')->all())->toBe([$firstSet->id, $secondSet->id]);
    expect($firstSet->refresh()->songs->pluck('id')->all())->toBe([$earlySong->id, $lateSong->id]);
    expect($earlySong->refresh()->slots->pluck('name')->all())->toBe(['vocals', 'bass']);
});
