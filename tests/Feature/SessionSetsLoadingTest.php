<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('lazy session sets endpoint renders slot rows without a blade error', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Lazy Sets Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Lazy Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Lazy Artist',
        'title' => 'Lazy Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Lazy Set')
        ->assertSee('Lazy Song')
        ->assertSee('Bass');
});

test('session routes use descriptive slugs but resolve by stable id', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Original Friendly Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $oldSessionUrl = route('sessions.show', $session);
    $oldSetsUrl = route('sessions.sets', $session);

    expect($oldSessionUrl)->toContain('/sessions/'.$session->id.'-original-friendly-session');
    expect($oldSetsUrl)->toContain('/sessions/'.$session->id.'-original-friendly-session/sets');

    $session->update(['name' => 'Renamed Friendly Session']);

    $this->actingAs($owner)
        ->get($oldSessionUrl)
        ->assertOk()
        ->assertSee('Renamed Friendly Session');

    $this->actingAs($owner)
        ->get($oldSetsUrl, ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();
});

test('session page shows set loading errors before loading placeholders', function () {
    $view = file_get_contents(resource_path('views/sessions/show.blade.php'));

    expect($view)->toContain('x-text="error"');
    expect($view)->toContain('x-show="!loaded && !error"');
    expect(strpos($view, 'x-show="error"'))->toBeLessThan(strpos($view, 'x-show="!loaded && !error"'));
});

test('session activity endpoint batches approval count and open song slot updates', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create(['name' => 'Recommended Player']);

    $session = JamSession::query()->create([
        'name' => 'Dynamic Slot Row Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Dynamic Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $firstSong = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Dynamic Artist',
        'title' => 'Dynamic Song',
        'notes' => null,
        'position' => 1,
    ]);

    $firstSlot = Slot::query()->create([
        'song_id' => $firstSong->id,
        'name' => 'bass',
        'position' => 1,
    ]);

    $secondSong = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Second Artist',
        'title' => 'Second Song',
        'notes' => null,
        'position' => 2,
    ]);

    Slot::query()->create([
        'song_id' => $secondSong->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $target->id,
    ]);

    $otherSession = JamSession::query()->create([
        'name' => 'Other Session',
        'date' => now()->addWeeks(2)->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $otherSet = Set::query()->create([
        'name' => 'Other Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $otherSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $otherSong = Song::query()->create([
        'set_id' => $otherSet->id,
        'artist' => 'Other Artist',
        'title' => 'Other Song',
        'notes' => null,
        'position' => 1,
    ]);

    SlotAssignment::query()->create([
        'slot_id' => $firstSlot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee(route('sessions.activity', $session), false)
        ->assertSee('x-on:session-song-opened.window="$store.approvals.refresh()"', false);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('x-bind:data-set-open="(!setCollapsed).toString()"', false)
        ->assertSee('x-bind:data-song-open="(!songCollapsed).toString()"', false)
        ->assertSee('data-song-slots-id="'.$firstSong->id.'"', false)
        ->assertDontSee('data-song-slots-body data-song-id=', false)
        ->assertSee('session-song-opened', false);

    $response = $this->actingAs($target)
        ->get(route('sessions.activity', [
            'jamSession' => $session,
            'song_ids' => [$firstSong->id, $secondSong->id, $otherSong->id],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonPath('approval_count', 1)
        ->assertJsonStructure([
            'approval_count',
            'songs' => [
                (string) $firstSong->id => ['slots_html'],
                (string) $secondSong->id => ['slots_html'],
            ],
        ])
        ->assertJsonMissingPath('songs.'.$otherSong->id);

    expect($response->json('songs.'.$firstSong->id.'.slots_html'))
        ->toContain('Bass')
        ->toContain('You');

    expect($response->json('songs.'.$secondSong->id.'.slots_html'))
        ->toContain('Vocals')
        ->toContain('You');

    $script = file_get_contents(resource_path('js/components/lazySessionSets.js'));
    $store = file_get_contents(resource_path('js/stores/approvals.js'));

    expect($script)
        ->toContain('hasOpenSongCard()')
        ->toContain('refreshOpenSongCards()')
        ->toContain('patchOpenSongSlots')
        ->toContain('[data-session-set-card][data-set-open="true"] [data-session-song-card][data-song-open="true"]');

    expect($store)
        ->toContain('useRefreshProvider')
        ->toContain('clearRefreshProvider');
});
