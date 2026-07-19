<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
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
