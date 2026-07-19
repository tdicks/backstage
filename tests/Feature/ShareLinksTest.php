<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set share page is available to guests with social metadata', function () {
    $owner = User::factory()->create(['name' => 'User A']);
    $session = JamSession::create([
        'name' => 'Sunday Jam',
        'date' => now()->addDays(7),
        'description' => null,
    ]);
    $set = Set::create([
        'name' => 'Heavy Openers',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    Song::create([
        'set_id' => $set->id,
        'artist' => 'Black Sabbath',
        'title' => 'Paranoid',
        'notes' => null,
        'position' => 1,
    ]);
    Song::create([
        'set_id' => $set->id,
        'artist' => 'Muse',
        'title' => 'Hysteria',
        'notes' => null,
        'position' => 2,
    ]);

    expect(route('share.set', $set))->toContain('/share/set/'.$set->id.'-heavy-openers');

    $this->get(route('share.set', $set))
        ->assertOk()
        ->assertSee('User A&#039;s set - Heavy Openers', false)
        ->assertSee('property="og:title" content="User A&#039;s set - Heavy Openers"', false)
        ->assertSee('Black Sabbath - Paranoid; Muse - Hysteria')
        ->assertSee('property="og:url" content="'.route('share.set', $set).'"', false);
});

test('session share page is available to guests with feature set metadata', function () {
    $owner = User::factory()->create(['name' => 'Feature Owner']);
    $session = JamSession::create([
        'name' => 'Feature Jam',
        'date' => now()->addDays(10),
        'description' => null,
    ]);

    Set::create([
        'name' => 'Headline Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'feature_set' => true,
    ]);
    Set::create([
        'name' => 'Regular Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
        'feature_set' => false,
    ]);

    expect(route('share.session', $session))->toContain('/share/session/'.$session->id.'-feature-jam');

    $this->get(route('share.session', $session))
        ->assertOk()
        ->assertSee('Feature Jam')
        ->assertSee('Feature Owner - Headline Set')
        ->assertDontSee('Regular Set')
        ->assertSee('property="og:url" content="'.route('share.session', $session).'"', false);
});

test('share links resolve by stable id when names change', function () {
    $owner = User::factory()->create(['name' => 'Stable Owner']);
    $session = JamSession::create([
        'name' => 'Original Session Name',
        'date' => now()->addDays(12),
        'description' => null,
    ]);
    $set = Set::create([
        'name' => 'Original Set Name',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $oldSessionUrl = route('share.session', $session);
    $oldSetUrl = route('share.set', $set);

    $session->update(['name' => 'Renamed Session']);
    $set->update(['name' => 'Renamed Set']);

    $this->get($oldSessionUrl)
        ->assertOk()
        ->assertSee('Renamed Session');

    $this->get($oldSetUrl)
        ->assertOk()
        ->assertSee('Stable Owner&#039;s set - Renamed Set', false);
});
