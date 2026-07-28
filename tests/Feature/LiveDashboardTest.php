<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

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

test('non-live jam session live dashboard shows the standby message', function () {
    $session = JamSession::create([
        'name' => 'Standby Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => false,
    ]);

    $this->get(route('sessions.live.dashboard', $session))
        ->assertOk()
        ->assertSee('This jam session')
        ->assertSee('started yet or is finished.');
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
        'is_live' => true,
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

    $data = $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk()
        ->json();

    expect($data)->toHaveKey('sets')
        ->and($data['sets'])->toHaveCount(1)
        ->and($data['sets'][0]['id'])->toBe($set->id)
        ->and($data['sets'][0]['status'])->toBe('pending')
        ->and($data['sets'][0]['health'])->toBe(0)
        ->and($data['sets'][0]['feature_set'])->toBeTrue()
        ->and($data['sets'][0]['created_at'])->not->toBeNull()
        ->and($data['jam_manager'])->toBeNull();
});

test('live data reflects cached state', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create([
        'name' => 'Reflect Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
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
        'sets' => [['set_id' => $set->id, 'status' => 'playing_now', 'order' => 0]],
        'updated_at' => now()->toIso8601String(),
    ], 3600);

    $this->actingAs($admin)
        ->getJson(route('sessions.live.data', $session))
        ->assertOk()
        ->assertJsonPath('sets.0.status', 'playing_now');
});
