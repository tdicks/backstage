<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set and song cards render dropdown menu controls', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Menu Set',
        'description' => 'Menu description',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Menu Artist',
        'title' => 'Menu Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('aria-label="Set actions"', false)
        ->assertSee('aria-label="Song actions"', false)
        ->assertSee('Summary')
        ->assertSee('Copy share link')
        ->assertSee('Edit Set')
        ->assertSee('Add Song')
        ->assertSee('Edit Song')
        ->assertSee('Add Slot')
        ->assertDontSee('aria-label="Edit Set"', false)
        ->assertDontSee('aria-label="Add Song"', false)
        ->assertDontSee('aria-label="Edit song"', false)
        ->assertDontSee('aria-label="Add slot"', false);
});

test('admin sees shield suffix on managed set and song menu items', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['name' => 'Set Owner']);

    $session = JamSession::query()->create([
        'name' => 'Admin Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Menu Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Admin Artist',
        'title' => 'Admin Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('text-sky-600 hover:text-sky-700 focus:ring-sky-400', false)
        ->assertSee('text-sky-700 hover:bg-sky-50 focus:bg-sky-50', false)
        ->assertSee('Edit Set 🛡️')
        ->assertSee('Add Song 🛡️')
        ->assertSee('Edit Song 🛡️')
        ->assertSee('Add Slot 🛡️')
        ->assertSee('sr-only"> Admin action</span>', false);
});

test('admin does not see shield suffix on their own set menu items', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::query()->create([
        'name' => 'Admin Own Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Own Menu Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Own Artist',
        'title' => 'Own Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertDontSee('Edit Set 🛡️')
        ->assertDontSee('Add Song 🛡️')
        ->assertDontSee('Edit Song 🛡️')
        ->assertDontSee('Add Slot 🛡️')
        ->assertDontSee('sr-only"> Admin action</span>', false);
});
