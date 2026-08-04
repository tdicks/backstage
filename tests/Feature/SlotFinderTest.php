<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('find a slot page lists visible upcoming open slots with coverage matches first', function () {
    $user = User::factory()->create(['slot_coverage' => ['drums']]);
    $visibleSession = JamSession::query()->create([
        'name' => 'Visible Future Jam',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);
    $hiddenSession = JamSession::query()->create([
        'name' => 'Hidden Future Jam',
        'date' => now()->addDays(4),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => true,
        'is_archived' => false,
    ]);
    $pastSession = JamSession::query()->create([
        'name' => 'Past Jam',
        'date' => now()->subDay(),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $visibleSet = Set::create([
        'name' => 'Visible Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $visibleSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $hiddenSet = Set::create([
        'name' => 'Hidden Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $hiddenSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $pastSet = Set::create([
        'name' => 'Past Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $pastSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $visibleSong = Song::create([
        'set_id' => $visibleSet->id,
        'artist' => 'Future Artist',
        'title' => 'Future Song',
        'notes' => null,
        'position' => 1,
    ]);

    $hiddenSong = Song::create([
        'set_id' => $hiddenSet->id,
        'artist' => 'Hidden Artist',
        'title' => 'Hidden Song',
        'notes' => null,
        'position' => 1,
    ]);

    $pastSong = Song::create([
        'set_id' => $pastSet->id,
        'artist' => 'Past Artist',
        'title' => 'Past Song',
        'notes' => null,
        'position' => 1,
    ]);

    $drumsSlot = Slot::create([
        'song_id' => $visibleSong->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    $vocalsSlot = Slot::create([
        'song_id' => $visibleSong->id,
        'name' => 'vocals',
        'position' => 2,
    ]);

    Slot::create([
        'song_id' => $hiddenSong->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $pastSong->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('slot-finder.index'));

    $response
        ->assertOk()
        ->assertDontSee('How the Slot Finder works')
        ->assertViewHas('sessionGroups', function ($sessionGroups) {
            return $sessionGroups->count() === 1
                && $sessionGroups->first()['slot_count'] === 2;
        })
        ->assertSee('Find a Slot')
        ->assertSee('Visible Future Jam')
        ->assertSee('Future Artist - Future Song')
        ->assertDontSee('Hidden Song')
        ->assertDontSee('Past Song')
        ->assertSee('@click="toggle()"', false)
        ->assertDontSee('Take Slot')
        ->assertDontSee('Request Slot');

    expect($response->viewData('sessionGroups')->first()['sets']->first()['songs']->first()['slots']->pluck('name')->all())
        ->toBe(['drums', 'vocals']);
});

test('find a slot page hides slots marked as wont cover', function () {
    $user = User::factory()->create(['slot_coverage' => ['drums' => 'wont_cover']]);
    $session = JamSession::query()->create([
        'name' => 'Future Jam',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $set = Set::create([
        'name' => 'Future Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('slot-finder.index'));

    $response
        ->assertOk()
        ->assertDontSee('Artist - Song');
});

test('find a slot page shows the free for all icon on free for all sets', function () {
    $user = User::factory()->create();
    $session = JamSession::query()->create([
        'name' => 'Free for All Jam',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => false,
        'is_archived' => false,
    ]);

    $set = Set::create([
        'name' => 'Free for All Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'free_for_all' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('slot-finder.index'));

    $response
        ->assertOk()
        ->assertSee('title="Free for all mode"', false)
        ->assertSee('Free for all mode');
});

test('find a slot page shows hidden icons for hidden sessions and sets', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::query()->create([
        'name' => 'Hidden Jam Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
        'is_hidden' => true,
        'is_archived' => false,
    ]);

    $set = Set::create([
        'name' => 'Hidden Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'is_hidden' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Hidden Artist',
        'title' => 'Hidden Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('slot-finder.index'));

    $response
        ->assertOk()
        ->assertSee('Jam session is hidden from non-admin users')
        ->assertSee('Hidden set')
        ->assertSee('Hidden jam session')
        ->assertSee('inset_0_0_8px_rgb(125_211_252_/_0.65)', false)
        ->assertSee('inset_0_0_20px_rgb(186_230_253_/_0.55)', false);
});

test('find a slot page appears in navigation for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Find a Slot')
        ->assertSee(route('slot-finder.index'), false);
});
