<?php

use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\Set as JamSet;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSessionWithSet(User $owner): array
{
    $session = JamSession::query()->create([
        'name' => 'Sunday Jam',
        'date' => now()->toDateString(),
        'description' => null,
    ]);

    $set = JamSet::query()->create([
        'name' => 'Set A',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    return [$session, $set];
}

test('summary endpoint includes checked-in status for assigned players', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $player = User::factory()->create(['name' => 'Casey Bass']);

    [$session, $set] = createSessionWithSet($admin);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'The Tune',
        'notes' => null,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => $player->id,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Guest Singer',
    ]);

    JamSessionSignIn::query()->create([
        'jam_session_id' => $session->id,
        'user_id' => $player->id,
        'signed_in_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('sets.summary', $set));

    $response->assertOk();
    $response->assertJsonPath('songs.0.artist', 'The Band');
    $response->assertJsonPath('songs.0.slot_map.bass.display', 'Casey Bass');
    $response->assertJsonPath('songs.0.slot_map.bass.checked_in', true);
    $response->assertJsonPath('songs.0.slot_map.bass.is_manual', false);
    $response->assertJsonPath('songs.0.slot_map.bass.is_current_user', false);
    $response->assertJsonPath('songs.0.slot_map.drums.state', 'open');
    $response->assertJsonPath('songs.0.slot_map.vocals.display', 'Guest Singer');
    $response->assertJsonPath('songs.0.slot_map.vocals.is_manual', true);
    $response->assertJsonPath('songs.0.slot_map.vocals.is_current_user', false);
});

test('set routes use descriptive slugs but resolve by stable id', function () {
    $owner = User::factory()->create();
    [, $set] = createSessionWithSet($owner);

    $oldSummaryUrl = route('sets.summary', $set);

    expect($oldSummaryUrl)->toContain('/sets/'.$set->id.'-set-a/summary');

    $set->update(['name' => 'Renamed Set A']);

    $this->actingAs($owner)
        ->getJson($oldSummaryUrl)
        ->assertOk();
});

test('summary endpoint requires authentication', function () {
    $owner = User::factory()->create();
    [, $set] = createSessionWithSet($owner);

    $this->get(route('sets.summary', $set))
        ->assertRedirect(route('login'));
});

test('summary endpoint uses You for current user assignee display', function () {
    $owner = User::factory()->create();

    [, $set] = createSessionWithSet($owner);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'Current User Song',
        'notes' => null,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->getJson(route('sets.summary', $set));

    $response->assertOk();
    $response->assertJsonPath('songs.0.slot_map.bass.display', 'You');
    $response->assertJsonPath('songs.0.slot_map.bass.is_current_user', true);
});
