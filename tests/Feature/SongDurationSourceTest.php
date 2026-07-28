<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Song duration & source persistence
// ---------------------------------------------------------------------------

test('set owner can add a song with deezer duration and source', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Duration Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Duration Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->postJson(route('songs.store', $set), [
            'artist' => 'AC/DC',
            'title' => 'Highway to Hell',
            'duration' => 208,
            'source' => 'deezer',
        ])
        ->assertOk();

    $song = Song::query()->where('set_id', $set->id)->first();

    expect($song)->not->toBeNull()
        ->and($song->duration)->toBe(208)
        ->and($song->source)->toBe('deezer');
});

test('set owner can add a song without duration or source', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'No Duration Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'No Duration Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->postJson(route('songs.store', $set), [
            'artist' => 'Led Zeppelin',
            'title' => 'Stairway to Heaven',
        ])
        ->assertOk();

    $song = Song::query()->where('set_id', $set->id)->first();

    expect($song)->not->toBeNull()
        ->and($song->duration)->toBeNull()
        ->and($song->source)->toBeNull();
});

test('set duration is calculated correctly in live data', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Duration Calc Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
    ]);

    $set = Set::create([
        'name' => 'Duration Calc Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    // A song with deezer source and duration counts
    Song::create([
        'set_id' => $set->id,
        'artist' => 'AC/DC',
        'title' => 'Back in Black',
        'notes' => null,
        'position' => 1,
        'duration' => 255,
        'source' => 'deezer',
    ]);

    // A song without source should NOT add to duration
    Song::create([
        'set_id' => $set->id,
        'artist' => 'Led Zeppelin',
        'title' => 'Whole Lotta Love',
        'notes' => null,
        'position' => 2,
        'duration' => 330,
        'source' => null,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk();

    // Only the song with source should be included (255 seconds)
    expect($response->json('sets.0.duration_seconds'))->toBe(255)
        ->and($response->json('sets.0.has_duration_estimate'))->toBeTrue();
});

test('live data does not offer a duration estimate when a set contains an undetermined song duration', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Incomplete Duration Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
    ]);

    $set = Set::create([
        'name' => 'Incomplete Duration Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    Song::create([
        'set_id' => $set->id,
        'artist' => 'Known Artist',
        'title' => 'Known Duration',
        'position' => 1,
        'duration' => 255,
        'source' => 'deezer',
    ]);

    Song::create([
        'set_id' => $set->id,
        'artist' => 'Unknown Artist',
        'title' => 'Unknown Duration',
        'position' => 2,
        'duration' => null,
        'source' => null,
    ]);

    $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk()
        ->assertJsonPath('sets.0.has_duration_estimate', false);
});
