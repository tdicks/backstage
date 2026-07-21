<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

// ---------------------------------------------------------------------------
// Live Jam Management Dashboard
// ---------------------------------------------------------------------------

test('admin can access live jam management dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Live Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.live.manage', $session))
        ->assertOk()
        ->assertViewIs('sessions.live.manage');
});

test('admin can mark a jam session as live', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Live Flag Jam',
        'date' => now()->addDay(),
        'description' => null,
        'allow_checkins' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('sessions.update', $session), [
            'name' => $session->name,
            'date' => $session->date->toDateString(),
            'description' => $session->description,
            'allow_checkins' => '1',
            'is_live' => '1',
        ])
        ->assertRedirect();

    expect($session->refresh()->is_live)->toBeTrue();
});

test('marking a jam session as not live clears live state cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Cache Clear Live Jam',
        'date' => now()->addDay(),
        'description' => null,
        'allow_checkins' => true,
        'is_live' => true,
    ]);

    $finishedSet = Set::create([
        'name' => 'Finished Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    Cache::put('live_jam_session:'.$session->id, [
        'sets' => [['set_id' => $finishedSet->id, 'status' => 'finished', 'order' => 0]],
        'updated_at' => now()->toIso8601String(),
    ], 3600);

    $this->actingAs($admin)
        ->patch(route('sessions.update', $session), [
            'name' => $session->name,
            'date' => $session->date->toDateString(),
            'description' => $session->description,
            'allow_checkins' => '1',
            'is_live' => '0',
        ])
        ->assertRedirect();

    expect($session->refresh()->is_live)->toBeFalse()
        ->and($finishedSet->refresh()->performed)->toBeTrue()
        ->and(Cache::has('live_jam_session:'.$session->id))->toBeFalse();
});

test('non-admin cannot access live jam management dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $session = JamSession::create([
        'name' => 'Live Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $this->actingAs($user)
        ->get(route('sessions.live.manage', $session))
        ->assertForbidden();
});

test('public can access live jam participant dashboard', function () {
    $session = JamSession::create([
        'name' => 'Public Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $this->get(route('sessions.live.dashboard', $session))
        ->assertOk()
        ->assertViewIs('sessions.live.dashboard');
});

test('live jam session page shows a live notice', function () {
    $user = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Live Notice Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('This jam session is now live');
});

test('short live code redirects to live dashboard', function () {
    $session = JamSession::create([
        'name' => 'Short Code Jam',
        'date' => now()->addDay(),
        'description' => null,
        'live_code' => 'XgUk',
    ]);

    $this->get(route('sessions.live.short', $session->live_code))
        ->assertRedirect(route('sessions.live.dashboard', $session));
});

test('live data endpoint returns set list with health', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Data Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Test Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'feature_set' => true,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk();

    $data = $response->json();

    expect($data)->toHaveKey('sets')
        ->and($data['sets'])->toHaveCount(1)
        ->and($data['sets'][0]['id'])->toBe($set->id)
        ->and($data['sets'][0]['status'])->toBe('pending')
        ->and($data['sets'][0]['health'])->toBe(0)
        ->and($data['sets'][0]['feature_set'])->toBeTrue();
});

test('admin can update live state via cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Cache Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Cache Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($admin)
        ->postJson(route('sessions.live.update', $session), [
            'sets' => [
                ['set_id' => $set->id, 'status' => 'playing_now', 'order' => 0],
            ],
        ])
        ->assertOk()
        ->assertJson(['message' => 'Live state updated.']);

    $cached = Cache::get('live_jam_session:'.$session->id);

    expect($cached)->not->toBeNull()
        ->and($cached['sets'][0]['set_id'])->toBe($set->id)
        ->and($cached['sets'][0]['status'])->toBe('playing_now');
});

test('live data reflects cached state', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Reflect Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Reflect Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    Cache::put('live_jam_session:'.$session->id, [
        'sets' => [
            ['set_id' => $set->id, 'status' => 'playing_now', 'order' => 0],
        ],
        'updated_at' => now()->toIso8601String(),
    ], 3600);

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk();

    $data = $response->json();

    expect($data['sets'][0]['status'])->toBe('playing_now');
});

test('live set details are persisted in live state cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Live Set Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $this->actingAs($admin)
        ->postJson(route('sessions.live.update', $session), [
            'sets' => [[
                'set_id' => 'live_12345_test',
                'status' => 'coming_up',
                'order' => 0,
                'isLiveSet' => true,
                'liveSetData' => [
                    'name' => 'Edited Live Set',
                    'owner' => 'House Band',
                    'participants' => 'Alex, Sam',
                    'details' => 'Updated details from the edit modal.',
                ],
            ]],
        ])
        ->assertOk();

    $cached = Cache::get('live_jam_session:'.$session->id);

    expect($cached['sets'][0]['liveSetData']['name'])->toBe('Edited Live Set')
        ->and($cached['sets'][0]['liveSetData']['details'])->toBe('Updated details from the edit modal.');

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk();

    $liveSet = collect($response->json('sets'))->firstWhere('id', 'live_12345_test');

    expect($liveSet)->not->toBeNull()
        ->and($liveSet['name'])->toBe('Edited Live Set')
        ->and($liveSet['owner'])->toBe('House Band')
        ->and($liveSet['participants'])->toBe('Alex, Sam')
        ->and($liveSet['details'])->toBe('Updated details from the edit modal.');
});

test('admin can clear live state cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Clear Jam',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    Cache::put('live_jam_session:'.$session->id, ['sets' => [], 'updated_at' => null], 3600);

    $this->actingAs($admin)
        ->deleteJson(route('sessions.live.clear', $session))
        ->assertOk();

    expect(Cache::has('live_jam_session:'.$session->id))->toBeFalse();
});

test('set duration is calculated correctly in live data', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Duration Calc Jam',
        'date' => now()->addDay(),
        'description' => null,
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
    expect($response->json('sets.0.duration_seconds'))->toBe(255);
});
