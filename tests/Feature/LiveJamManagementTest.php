<?php

use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\JamStandardSong;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('admin can access live jam management dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Live Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true]);

    $this->actingAs($admin)->get(route('sessions.live.manage', $session))->assertOk()->assertViewIs('sessions.live.manage');
});

test('live management shows quick set catalog controls with checked-in capability matches', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $performer = User::factory()->create(['name' => 'Checked In Bass']);
    $session = JamSession::create(['name' => 'Live Catalog Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true, 'jam_manager_id' => $admin->id]);
    JamSessionSignIn::query()->create(['jam_session_id' => $session->id, 'user_id' => $performer->id, 'signed_in_at' => now()]);

    $catalogSong = JamStandardSong::query()->create(['artist' => 'Pixies', 'title' => 'Where Is My Mind?', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    $catalogSong->userSlots()->create(['user_id' => $performer->id, 'slot_name' => 'bass']);

    $this->actingAs($admin)
        ->get(route('sessions.live.manage', $session))
        ->assertOk()
        ->assertSee('Quick Set')
        ->assertSee('@open-live-quick-set.window="openLiveQuickSet()"', false)
        ->assertSee("new CustomEvent('open-live-quick-set')", false)
        ->assertSee('href="'.route('sessions.show', $session).'"', false)
        ->assertSee('Songs with checked-in performers appear first.')
        ->assertDontSee('Refreshing checked-in performers...')
        ->assertSee('refreshLiveQuickSetData()', false)
        ->assertSee('live-quick-set', false)
        ->assertSee('x-show="liveDisplayModalOpen" x-cloak data-modal-overlay', false)
        ->assertSee('isLiveQuickSetAssignmentDisabled', false)
        ->assertSee('isLiveQuickSetAssignmentSelected', false)
        ->assertSee('isLiveQuickSetSongFullyAssigned(song)', false)
        ->assertSee('topSuggestedUsers(song, slot.name)', false)
        ->assertSee('slotLabel(slot.name)', false)
        ->assertSee('border-emerald-200 bg-emerald-50', false)
        ->assertSee('border-emerald-100 bg-emerald-50/50', false)
        ->assertSee('selectedLiveSongIds.includes(song.id)', false)
        ->assertSee('cursor-pointer', false)
        ->assertSee('submitLiveQuickSet($event.target)', false)
        ->assertDontSee('live_song_slots', false)
        ->assertSee('Leave unassigned')
        ->assertSee('Other attendee');

    $this->actingAs($admin)
        ->getJson(route('sessions.live.quick-set-data', $session))
        ->assertOk()
        ->assertJsonPath('songs.0.artist', 'Pixies')
        ->assertJsonPath('songs.0.capable_user_ids.bass.0', $performer->id)
        ->assertJsonPath('attendees.0.name', 'Checked In Bass');
});

test('live quick set data excludes checked-in users who will not cover the slot', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $capable = User::factory()->create(['name' => 'Capable Bass']);
    $unavailable = User::factory()->create(['name' => 'Unavailable Bass', 'slot_coverage' => ['bass' => User::SLOT_COVERAGE_WONT]]);
    $session = JamSession::create(['name' => 'Availability Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true, 'jam_manager_id' => $admin->id]);
    JamSessionSignIn::query()->create(['jam_session_id' => $session->id, 'user_id' => $capable->id, 'signed_in_at' => now()->subMinutes(2)]);
    JamSessionSignIn::query()->create(['jam_session_id' => $session->id, 'user_id' => $unavailable->id, 'signed_in_at' => now()->subMinute()]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Muse', 'title' => 'Hysteria', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    $catalogSong->userSlots()->create(['user_id' => $capable->id, 'slot_name' => 'bass']);

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.quick-set-data', $session))
        ->assertOk()
        ->assertJsonPath('songs.0.capable_user_ids.bass.0', $capable->id);

    expect(collect($response->json('attendees'))->keyBy('id')->all())
        ->toHaveKey($capable->id)
        ->toHaveKey($unavailable->id)
        ->and($response->json('attendees'))->toContain([
            'id' => $unavailable->id,
            'name' => 'Unavailable Bass',
            'slot_coverage' => ['bass' => User::SLOT_COVERAGE_WONT],
        ]);
});

test('non-admin cannot access live jam management dashboard', function () {
    $user = User::factory()->create();
    $session = JamSession::create(['name' => 'Live Jam', 'date' => now()->addDay(), 'description' => null]);

    $this->actingAs($user)->get(route('sessions.live.manage', $session))->assertForbidden();
});

test('admin can mark a jam session as live', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Live Flag Jam', 'date' => now()->addDay(), 'description' => null, 'allow_checkins' => true]);

    $this->actingAs($admin)->patch(route('sessions.update', $session), [
        'name' => $session->name, 'date' => $session->date->toDateString(), 'description' => null, 'allow_checkins' => '1', 'is_live' => '1',
    ])->assertRedirect();

    expect($session->refresh()->is_live)->toBeTrue();
});

test('turning off live mode disables sign-ins and clears management state', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Live Shutdown Jam', 'date' => now()->addDay(), 'description' => null,
        'allow_checkins' => true, 'is_live' => true, 'jam_manager_id' => $admin->id,
    ]);
    $finishedSet = Set::create([
        'name' => 'Finished Set', 'description' => null, 'owner_id' => $owner->id,
        'jam_session_id' => $session->id, 'position' => 1, 'performed' => false, 'signups_open' => true,
    ]);
    Cache::put('live_jam_session:'.$session->id, [
        'sets' => [['set_id' => $finishedSet->id, 'status' => 'finished', 'order' => 0]],
        'updated_at' => now()->toIso8601String(),
    ], 3600);

    $this->actingAs($admin)->patch(route('sessions.update', $session), [
        'name' => $session->name, 'date' => $session->date->toDateString(), 'description' => null, 'allow_checkins' => '1', 'is_live' => '0',
    ])->assertRedirect();

    expect($session->refresh()->is_live)->toBeFalse()
        ->and($session->allow_checkins)->toBeFalse()
        ->and($session->jam_manager_id)->toBeNull()
        ->and($finishedSet->refresh()->performed)->toBeTrue()
        ->and(Cache::has('live_jam_session:'.$session->id))->toBeFalse();
});

test('admin can update and clear live state cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Cache Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true]);
    $set = Set::create([
        'name' => 'Cache Set', 'description' => null, 'owner_id' => $admin->id,
        'jam_session_id' => $session->id, 'position' => 1, 'performed' => false, 'signups_open' => true,
    ]);

    $this->actingAs($admin)->postJson(route('sessions.live.manager.claim', $session))->assertOk();
    $this->actingAs($admin)->postJson(route('sessions.live.update', $session), [
        'sets' => [['set_id' => $set->id, 'status' => 'playing_now', 'order' => 0]],
    ])->assertOk()->assertJson(['message' => 'Live state updated.']);

    expect(Cache::get('live_jam_session:'.$session->id)['sets'][0]['status'])->toBe('playing_now');

    $this->actingAs($admin)->deleteJson(route('sessions.live.clear', $session))->assertOk();

    expect(Cache::has('live_jam_session:'.$session->id))->toBeFalse();
});

test('live manager can release and reclaim control', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Manager Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true]);

    $this->actingAs($admin)->postJson(route('sessions.live.manager.claim', $session))->assertOk()->assertJsonPath('jam_manager.id', $admin->id);
    expect($session->refresh()->jam_manager_id)->toBe($admin->id);

    $this->actingAs($admin)->deleteJson(route('sessions.live.manager.release', $session))->assertOk()->assertJson(['jam_manager' => null]);
    expect($session->refresh()->jam_manager_id)->toBeNull();
});

test('live updates require the current jam manager', function () {
    $manager = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Restricted Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true]);
    $set = Set::create([
        'name' => 'Restricted Set', 'description' => null, 'owner_id' => $manager->id,
        'jam_session_id' => $session->id, 'position' => 1, 'performed' => false, 'signups_open' => true,
    ]);

    $this->actingAs($manager)->postJson(route('sessions.live.manager.claim', $session))->assertOk();
    $this->actingAs($otherAdmin)->postJson(route('sessions.live.update', $session), [
        'sets' => [['set_id' => $set->id, 'status' => 'playing_now', 'order' => 0]],
    ])->assertForbidden();
});

test('live set details are persisted in live state cache', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create(['name' => 'Live Set Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true]);

    $this->actingAs($admin)->postJson(route('sessions.live.manager.claim', $session))->assertOk();
    $this->actingAs($admin)->postJson(route('sessions.live.update', $session), [
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
    ])->assertOk();

    expect(Cache::get('live_jam_session:'.$session->id)['sets'][0]['liveSetData']['name'])->toBe('Edited Live Set');

    $liveSet = collect($this->getJson(route('sessions.live.data', $session))->json('sets'))->firstWhere('id', 'live_12345_test');

    expect($liveSet)->not->toBeNull()
        ->and($liveSet['owner'])->toBe('House Band')
        ->and($liveSet['participants'])->toBe('Alex, Sam')
        ->and($liveSet['details'])->toBe('Updated details from the edit modal.');
});
