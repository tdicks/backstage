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

    $catalogSong = JamStandardSong::query()->create(['artist' => 'Pixies', 'title' => 'Where Is My Mind?', 'duration' => 230, 'source' => 'deezer', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    $catalogSong->userSlots()->create(['user_id' => $performer->id, 'slot_name' => 'bass']);

    $this->actingAs($admin)
        ->get(route('sessions.live.manage', $session))
        ->assertOk()
        ->assertSee('Quick Set')
        ->assertSee('@open-live-quick-set.window="openLiveQuickSet()"', false)
        ->assertSee("new CustomEvent('open-live-quick-set')", false)
        ->assertSee('href="'.route('sessions.show', $session).'"', false)
        ->assertSee('Assemble a quick set from the standards list.')
        ->assertSee('live-quick-set', false)
        ->assertSee('x-show="liveDisplayModalOpen" x-cloak data-modal-overlay', false)
        ->assertSee('selectedLiveSongIds.includes(song.id)', false)
        ->assertSee(':checked="selectedLiveSongIds.includes(song.id)"', false)
        ->assertSee('submitLiveQuickSet($event.target)', false)
        ->assertSee("@modal-closed.window=\"if (\$event.detail.name === 'live-quick-set')", false)
        ->assertSee('closeLiveQuickSet();', false)
        ->assertSee('Estimated set duration')
        ->assertSee('x-ref="liveQuickSetAssignmentList"', false)
        ->assertSee('aria-label="Collapse song assignments"', false)
        ->assertDontSee('hover:bg-black/5', false)
        ->assertSee('Collapse song assignments')
        ->assertSee('One or more songs do not have a duration, cannot estimate set time')
        ->assertDontSee('live_song_slots', false)
        ->assertSee('Leave unassigned')
        ->assertSee('Other attendee');

    $this->actingAs($admin)
        ->getJson(route('sessions.live.quick-set-data', $session))
        ->assertOk()
        ->assertJsonPath('songs.0.artist', 'Pixies')
        ->assertJsonPath('songs.0.duration', 230)
        ->assertJsonPath('songs.0.source', 'deezer')
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

test('live quick set ranks conflict-aware coverage with confirmed and global fallback capabilities', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $multiPartPerformer = User::factory()->create(['name' => 'Multi Part Performer']);
    $globalFallbackPerformer = User::factory()->create([
        'name' => 'Global Drummer',
        'slot_coverage' => ['drums' => User::SLOT_COVERAGE_CAN],
    ]);
    $unavailablePerformer = User::factory()->create([
        'name' => 'Unavailable Bassist',
        'slot_coverage' => ['bass' => User::SLOT_COVERAGE_WONT],
    ]);
    $session = JamSession::create([
        'name' => 'Coverage Ranking Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
        'jam_manager_id' => $admin->id,
    ]);

    foreach ([$multiPartPerformer, $globalFallbackPerformer, $unavailablePerformer] as $performer) {
        JamSessionSignIn::query()->create([
            'jam_session_id' => $session->id,
            'user_id' => $performer->id,
            'signed_in_at' => now(),
        ]);
    }

    $compatibleSong = JamStandardSong::query()->create(['artist' => 'Compatible', 'title' => 'Two Parts', 'is_active' => true]);
    $compatibleSong->slots()->create(['name' => 'vocals', 'position' => 1]);
    $compatibleSong->slots()->create(['name' => 'rhythm_guitar', 'position' => 2]);
    $compatibleSong->userSlots()->create(['user_id' => $multiPartPerformer->id, 'slot_name' => 'vocals']);
    $compatibleSong->userSlots()->create(['user_id' => $multiPartPerformer->id, 'slot_name' => 'rhythm_guitar']);

    $conflictSong = JamStandardSong::query()->create(['artist' => 'Conflicted', 'title' => 'Three Parts', 'is_active' => true]);
    $conflictSong->slots()->create(['name' => 'bass', 'position' => 1]);
    $conflictSong->slots()->create(['name' => 'rhythm_guitar', 'position' => 2]);
    $conflictSong->slots()->create(['name' => 'drums', 'position' => 3]);
    $conflictSong->userSlots()->create(['user_id' => $multiPartPerformer->id, 'slot_name' => 'bass']);
    $conflictSong->userSlots()->create(['user_id' => $multiPartPerformer->id, 'slot_name' => 'rhythm_guitar']);

    $fallbackSong = JamStandardSong::query()->create(['artist' => 'Fallback', 'title' => 'Potential Only', 'is_active' => true]);
    $fallbackSong->slots()->create(['name' => 'drums', 'position' => 1]);

    $excludedSong = JamStandardSong::query()->create(['artist' => 'Excluded', 'title' => 'No Bassist', 'is_active' => true]);
    $excludedSong->slots()->create(['name' => 'bass', 'position' => 1]);
    $excludedSong->userSlots()->create(['user_id' => $unavailablePerformer->id, 'slot_name' => 'bass']);

    $response = $this->actingAs($admin)
        ->getJson(route('sessions.live.quick-set-data', $session))
        ->assertOk()
        ->assertJsonPath('songs.0.artist', 'Compatible')
        ->assertJsonPath('songs.0.covered_slot_count', 2)
        ->assertJsonPath('songs.0.confirmed_assignment_count', 2)
        ->assertJsonPath('songs.1.artist', 'Conflicted')
        ->assertJsonPath('songs.1.covered_slot_count', 2)
        ->assertJsonPath('songs.1.confirmed_assignment_count', 1)
        ->assertJsonPath('songs.1.capable_user_ids.drums.0', $globalFallbackPerformer->id)
        ->assertJsonPath('songs.2.artist', 'Fallback')
        ->assertJsonPath('songs.2.covered_slot_count', 1)
        ->assertJsonPath('songs.2.confirmed_assignment_count', 0)
        ->assertJsonPath('timing.checked_in_user_count', 3)
        ->assertJsonPath('timing.candidate_song_count', 3);

    expect($response->json('songs'))->not->toContain(['id' => $excludedSong->id]);
    expect(Cache::get('live-quick-set:timing-summary')['last_sample'])->toMatchArray([
        'jam_session_id' => $session->id,
        'checked_in_user_count' => 3,
        'candidate_song_count' => 3,
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
