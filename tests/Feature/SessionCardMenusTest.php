<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
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

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Menu Artist',
        'title' => 'Menu Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'manual_performer_name' => 'Guest Singer',
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('aria-label="Set actions"', false)
        ->assertSee('aria-label="Song actions"', false)
        ->assertSee('Summary')
        ->assertSee('Edit Set')
        ->assertSee('Add Song')
        ->assertSee('Edit Song')
        ->assertSee('Add Slot')
        ->assertSee('Clear Slot')
        ->assertSee('Edit slot')
        ->assertSee('Copy Direct Link')
        ->assertSee('mobile-song-move', false)
        ->assertSee('mobile-slot-move', false)
        ->assertSee('fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4', false)
        ->assertSee('flex w-full max-w-lg max-h-[calc(100dvh-2rem)] flex-col overflow-y-auto rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl sm:max-h-[calc(100dvh-4rem)]', false)
        ->assertSee('#set-'.$set->id, false)
        ->assertSee('#song-'.$song->id, false)
        ->assertSee('#slot-'.$slot->id, false)
        ->assertSee("x-bind:title=\"assignmentIsManual ? 'Manually assigned' : ''\"", false)
        ->assertDontSee('aria-label="Admin"', false)
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
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('text-sky-700 hover:bg-sky-50 focus:bg-sky-50', false)
        ->assertSee('Edit Set')
        ->assertSee('Add Song')
        ->assertSee('Edit Song')
        ->assertSee('Add Slot')
        ->assertSee('mr-1 inline h-4 w-4 text-sky-500', false)
        ->assertDontSee('🛡️')
        ->assertSee('sr-only"> Admin action</span>', false);
});

test('non-manager still sees song actions menu with direct link action', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Guest Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Guest Menu Set',
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
        'artist' => 'Guest Artist',
        'title' => 'Guest Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('aria-label="Song actions"', false)
        ->assertSee('Copy Direct Link')
        ->assertDontSee('Add Slot')
        ->assertDontSee('Edit Song');
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
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertDontSee('mr-1 inline h-4 w-4 text-sky-500', false)
        ->assertDontSee('🛡️')
        ->assertDontSee('sr-only"> Admin action</span>', false);
});

test('clear slot action is hidden when the current user has the slot', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Owner Slot Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Owner Slot Menu Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Owner Slot Artist',
        'title' => 'Owner Slot Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Release slot')
        ->assertSee('Clear Slot')
        ->assertSee('x-show="!slotIsOpen && !assignedToCurrentUser"', false);
});
