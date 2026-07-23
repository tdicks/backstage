<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->jamSession = JamSession::create([
        'name' => 'Test Jam',
        'date' => now()->addDays(1),
        'description' => 'Test session',
        'is_live' => true,
    ]);
});

it('persists state to cache and returns it', function () {
    // Create sets
    $set1 = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Set 1',
        'owner_id' => $this->user->id,
        'position' => 0,
    ]);

    $set2 = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Set 2',
        'owner_id' => $this->user->id,
        'position' => 1,
    ]);

    $set3 = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Set 3',
        'owner_id' => $this->user->id,
        'position' => 2,
    ]);

    // Initial load - should have coming_up assigned
    $url = "/sessions/{$this->jamSession->routeSlug()}/live/data";
    $response = $this->getJson($url);

    expect($response->status())->toBe(200);
    $data = $response->json();
    $returnedSets = $data['sets'];

    expect(count($returnedSets))->toBe(3);

    // Check initial status assignment (first gets playing_now, second gets coming_up)
    $initialHasComingUp = collect($returnedSets)->some(fn ($s) => $s['status'] === 'coming_up');
    expect($initialHasComingUp)->toBeTrue('Initial load should assign coming_up');

    // Now save state to cache - mark first set as playing_now
    $updateUrl = "/sessions/{$this->jamSession->routeSlug()}/live/update";
    $response = $this->actingAs($this->user)
        ->postJson($updateUrl, [
            'sets' => [
                ['set_id' => $returnedSets[0]['id'], 'status' => 'playing_now', 'order' => -1],
                ['set_id' => $returnedSets[1]['id'], 'status' => 'coming_up', 'order' => 1],
                ['set_id' => $returnedSets[2]['id'], 'status' => 'pending', 'order' => 2],
            ],
        ]);

    expect($response->status())->toBe(200);

    // Verify cache was saved
    $cacheKey = "live_jam_session:{$this->jamSession->id}";
    expect(Cache::has($cacheKey))->toBeTrue('Cache should be set after update');

    $cachedState = Cache::get($cacheKey);
    expect($cachedState)->toBeArray();
    expect($cachedState['sets'])->toHaveCount(3);

    // Log the cache for debugging
    // ray('Cache state:', $cachedState);

    // Load data again - should return cached state
    $response = $this->getJson("/sessions/{$this->jamSession->routeSlug()}/live/data");
    $cachedSets = $response->json('sets');

    expect(count($cachedSets))->toBe(3);

    // Verify the statuses match what we saved
    $playingNowSet = collect($cachedSets)->firstWhere('id', $returnedSets[0]['id']);
    expect($playingNowSet['status'])->toBe('playing_now', 'First set should be playing_now from cache. Got: '.$playingNowSet['status']);

    $comingUpSet = collect($cachedSets)->firstWhere('id', $returnedSets[1]['id']);
    expect($comingUpSet['status'])->toBe('coming_up', 'Second set should be coming_up from cache. Got: '.$comingUpSet['status']);

    $pendingSet = collect($cachedSets)->firstWhere('id', $returnedSets[2]['id']);
    expect($pendingSet['status'])->toBe('pending', 'Third set should be pending from cache. Got: '.$pendingSet['status']);

    // Verify order is also preserved
    expect($playingNowSet['order'])->toBe(-1);
    expect($comingUpSet['order'])->toBe(1);
    expect($pendingSet['order'])->toBe(2);
});

test('live data excludes performed sets', function () {
    $performedSet = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Performed Set',
        'owner_id' => $this->user->id,
        'position' => 0,
        'performed' => true,
    ]);

    $upcomingSet = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Upcoming Set',
        'owner_id' => $this->user->id,
        'position' => 1,
        'performed' => false,
    ]);

    $response = $this->actingAs($this->user)->getJson("/sessions/{$this->jamSession->routeSlug()}/live/data");

    $response->assertOk();

    $returnedIds = collect($response->json('sets'))->pluck('id');

    expect($returnedIds)->toContain($upcomingSet->id)
        ->and($returnedIds)->not->toContain($performedSet->id);
});

test('saving live state normalizes each status stack to contiguous unique orders', function () {
    $sets = collect(range(1, 5))->map(fn (int $position) => Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => "Set {$position}",
        'owner_id' => $this->user->id,
        'position' => $position,
    ]));

    $this->jamSession->update(['jam_manager_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson(route('sessions.live.update', $this->jamSession), [
            'sets' => [
                ['set_id' => $sets[0]->id, 'status' => 'pending', 'order' => 0],
                ['set_id' => $sets[1]->id, 'status' => 'pending', 'order' => 1],
                ['set_id' => $sets[2]->id, 'status' => 'pending', 'order' => 2],
                ['set_id' => $sets[3]->id, 'status' => 'coming_up', 'order' => 0],
                ['set_id' => $sets[4]->id, 'status' => 'pending', 'order' => 4],
            ],
        ])
        ->assertOk();

    $this->actingAs($this->user)
        ->postJson(route('sessions.live.update', $this->jamSession), [
            'sets' => [
                ['set_id' => $sets[3]->id, 'status' => 'pending', 'order' => 0],
                ['set_id' => $sets[0]->id, 'status' => 'pending', 'order' => 0],
                ['set_id' => $sets[1]->id, 'status' => 'pending', 'order' => 1],
                ['set_id' => $sets[2]->id, 'status' => 'pending', 'order' => 2],
                ['set_id' => $sets[4]->id, 'status' => 'pending', 'order' => 4],
            ],
        ])
        ->assertOk();

    $cachedPendingOrders = collect(Cache::get("live_jam_session:{$this->jamSession->id}")['sets'])
        ->where('status', 'pending')
        ->pluck('order')
        ->sort()
        ->values()
        ->all();

    expect($cachedPendingOrders)->toBe([0, 1, 2, 3, 4]);
});

test('completed songs are persisted in live state and returned to dashboards', function () {
    $set = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Completed Song Set',
        'owner_id' => $this->user->id,
        'position' => 0,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Artists',
        'title' => 'The Completed Song',
        'position' => 0,
    ]);
    $this->jamSession->update(['jam_manager_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson(route('sessions.live.update', $this->jamSession), [
            'sets' => [[
                'set_id' => $set->id,
                'status' => 'playing_now',
                'order' => 0,
                'completed_song_ids' => [$song->id],
            ]],
        ])
        ->assertOk();

    expect(Cache::get("live_jam_session:{$this->jamSession->id}")['sets'][0]['completed_song_ids'])
        ->toBe([$song->id]);

    $this->getJson(route('sessions.live.data', $this->jamSession))
        ->assertOk()
        ->assertJsonPath('sets.0.songs.0.completed', true);
});

test('public song list collapse is persisted in live state and returned to dashboards', function () {
    $set = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Long Set',
        'owner_id' => $this->user->id,
        'position' => 0,
    ]);
    $this->jamSession->update(['jam_manager_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson(route('sessions.live.update', $this->jamSession), [
            'sets' => [[
                'set_id' => $set->id,
                'status' => 'coming_up',
                'order' => 0,
                'songs_collapsed' => true,
            ]],
        ])
        ->assertOk();

    expect(Cache::get("live_jam_session:{$this->jamSession->id}")['sets'][0]['songs_collapsed'])
        ->toBeTrue();

    $this->getJson(route('sessions.live.data', $this->jamSession))
        ->assertOk()
        ->assertJsonPath('sets.0.songs_collapsed', true);
});

test('jam manager can update a live slot assignment and receives assignment edit data', function () {
    $setOwner = User::factory()->create();
    $jamManager = User::factory()->create();
    $this->jamSession->update(['jam_manager_id' => $jamManager->id]);

    $set = Set::create([
        'jam_session_id' => $this->jamSession->id,
        'name' => 'Managed Set',
        'owner_id' => $setOwner->id,
        'position' => 0,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Artist',
        'title' => 'The Song',
        'position' => 0,
    ]);
    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($jamManager)
        ->patchJson(route('slots.update', $slot), [
            'name' => 'vocals',
            'manual_performer_name' => 'Guest Vocalist',
        ])
        ->assertOk()
        ->assertJsonPath('slot.manual_performer_name', 'Guest Vocalist');

    $this->actingAs($jamManager)
        ->getJson(route('sessions.live.data', $this->jamSession))
        ->assertOk()
        ->assertJsonPath('sets.0.songs.0.slots.0.slot_key', 'vocals')
        ->assertJsonPath('sets.0.songs.0.slots.0.manual_performer_name', 'Guest Vocalist');
});

test('non-live sessions do not return live sets', function () {
    $session = JamSession::create([
        'name' => 'Offline Jam',
        'date' => now()->addDays(1),
        'description' => 'Test session',
        'is_live' => false,
    ]);

    Set::create([
        'jam_session_id' => $session->id,
        'name' => 'Hidden Set',
        'owner_id' => $this->user->id,
        'position' => 0,
        'performed' => false,
    ]);

    $response = $this->actingAs($this->user)->getJson("/sessions/{$session->routeSlug()}/live/data");

    $response->assertOk()->assertJsonCount(0, 'sets');
});
