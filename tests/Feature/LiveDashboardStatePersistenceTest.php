<?php

use App\Models\JamSession;
use App\Models\Set;
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
    expect($playingNowSet['status'])->toBe('playing_now', 'First set should be playing_now from cache. Got: ' . $playingNowSet['status']);

    $comingUpSet = collect($cachedSets)->firstWhere('id', $returnedSets[1]['id']);
    expect($comingUpSet['status'])->toBe('coming_up', 'Second set should be coming_up from cache. Got: ' . $comingUpSet['status']);

    $pendingSet = collect($cachedSets)->firstWhere('id', $returnedSets[2]['id']);
    expect($pendingSet['status'])->toBe('pending', 'Third set should be pending from cache. Got: ' . $pendingSet['status']);

    // Verify order is also preserved
    expect($playingNowSet['order'])->toBe(-1);
    expect($comingUpSet['order'])->toBe(1);
    expect($pendingSet['order'])->toBe(2);
});
