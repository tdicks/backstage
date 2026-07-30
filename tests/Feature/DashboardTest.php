<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the Backstage welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Make the next jam happen.')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('authenticated users visiting the homepage are redirected to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('guests visiting the dashboard are redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users see their next jam session slots on the dashboard', function () {
    $user = User::factory()->create(['name' => 'Alex Player']);
    $otherUser = User::factory()->create();

    $earlierSession = JamSession::create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(3),
        'description' => null,
    ]);
    $laterSession = JamSession::create([
        'name' => 'Saturday Jam',
        'date' => now()->addDays(10),
        'description' => null,
    ]);

    $featuredSet = Set::create([
        'name' => 'Opening Set',
        'owner_id' => $otherUser->id,
        'jam_session_id' => $earlierSession->id,
        'position' => 1,
    ]);
    $laterSet = Set::create([
        'name' => 'Later Set',
        'owner_id' => $otherUser->id,
        'jam_session_id' => $laterSession->id,
        'position' => 1,
    ]);

    $featuredSong = Song::create([
        'set_id' => $featuredSet->id,
        'artist' => 'The Band',
        'title' => 'Opening Song',
        'position' => 1,
    ]);
    $laterSong = Song::create([
        'set_id' => $laterSet->id,
        'artist' => 'The Band',
        'title' => 'Later Song',
        'position' => 1,
    ]);

    Slot::create(['song_id' => $featuredSong->id, 'name' => 'guitar', 'position' => 1, 'user_id' => $user->id]);
    Slot::create(['song_id' => $laterSong->id, 'name' => 'bass', 'position' => 1, 'user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Welcome back, Alex Player')
        ->assertSee(route('dashboard'))
        ->assertSee('Friday Jam')
        ->assertSee('Opening Set')
        ->assertSee('Guitar on The Band - Opening Song')
        ->assertSee(route('sessions.show', $earlierSession).'#set-'.$featuredSet->id);

    expect($response->getContent())
        ->toContain('<ul class="mt-1 list-none space-y-1 text-sm text-slate-600">')
        ->toContain('<li>Guitar on The Band - Opening Song</li>')
        ->toContain('<h3 data-next-session-name class="mt-2 text-3xl font-semibold text-slate-900">Friday Jam</h3>')
        ->not->toContain('<h3 data-next-session-name class="mt-2 text-3xl font-semibold text-slate-900">Saturday Jam</h3>');
});

test('authenticated users without upcoming slots see the empty dashboard state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No upcoming slots yet')
        ->assertSee(route('sessions.index'));
});

test('dashboard shows a dismissible get started quest for new users', function () {
    $user = User::factory()->create([
        'bio' => null,
        'onboarding_dismissed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('Add something to your bio')
        ->assertSee('Sign up for a song')
        ->assertSee('Create your own set');

    $this->actingAs($user)
        ->post(route('dashboard.get-started.dismiss'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Get started');
});

test('dashboard keeps the get started quest visible until dismissed when all items are complete', function () {
    $user = User::factory()->create([
        'bio' => 'I play drums and love late-night jams.',
        'onboarding_dismissed_at' => null,
    ]);

    $jamSession = JamSession::create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'My first set',
        'owner_id' => $user->id,
        'jam_session_id' => $jamSession->id,
        'position' => 1,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'First Song',
        'position' => 1,
    ]);
    Slot::create(['song_id' => $song->id, 'name' => 'drums', 'position' => 1, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('all set')
        ->assertSee('Happy jamming!');
});

test('dashboard dismissing the get started quest over ajax returns no content', function () {
    $user = User::factory()->create([
        'onboarding_dismissed_at' => null,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->post(route('dashboard.get-started.dismiss'))
        ->assertNoContent();
});

test('dashboard shows quick links', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Quick links')
        ->assertSee(route('sessions.index'))
        ->assertSee(route('my-sets.index'))
        ->assertSee(route('directory.index'))
        ->assertSee(route('profile.edit'));
});
