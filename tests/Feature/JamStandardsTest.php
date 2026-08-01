<?php

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\JamStandardSong;
use App\Models\JamStandardSongRequest;
use App\Models\JamStandardUserSlot;
use App\Models\Set;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('jam standards page requires authentication', function () {
    $this->get(route('jam-standards.index'))
        ->assertRedirect(route('login'));
});

test('an admin can add a catalog song and receives duplicate warning for close matches', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    JamStandardSong::query()->create([
        'artist' => 'Metallica',
        'title' => 'Enter Sandman',
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('jam-standards.store'), [
            'artist' => 'Metallica',
            'title' => 'Enter Sandman (Live at Download)',
            'slot_names' => ['bass'],
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHas('warning')
        ->assertSessionHas('duplicateSuggestions', function (array $suggestions): bool {
            return collect($suggestions)->contains(function (array $song): bool {
                return $song['artist'] === 'Metallica' && $song['title'] === 'Enter Sandman';
            });
        });

    expect(JamStandardSong::query()->count())->toBe(2);
});

test('catalog song durations are persisted from Deezer selections', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('jam-standards.store'), [
            'artist' => 'Blondie',
            'title' => 'Heart of Glass',
            'duration' => 294,
            'source' => 'deezer',
            'slot_names' => ['vocals'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    expect(JamStandardSong::query()->sole())
        ->duration->toBe(294)
        ->source->toBe('deezer');
});

test('catalog songs and catalog requests require a template or manual slots', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('jam-standards.index'))
        ->post(route('jam-standards.store'), [
            'artist' => 'Pixies',
            'title' => 'Where Is My Mind?',
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHasErrors(['band_template_id', 'slot_names']);

    $this->actingAs($user)
        ->from(route('jam-standards.index'))
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'Pixies',
            'title' => 'Where Is My Mind?',
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHasErrors(['band_template_id', 'slot_names']);
});

test('a catalog song retains its selected template until an admin switches to manual slots', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $template = BandTemplate::query()->create(['name' => 'Rock Trio']);
    $template->slots()->create(['name' => 'bass']);
    $template->slots()->create(['name' => 'drums']);

    $this->actingAs($admin)
        ->post(route('jam-standards.store'), [
            'artist' => 'The Stooges',
            'title' => 'Search and Destroy',
            'band_template_id' => $template->id,
        ])
        ->assertRedirect(route('jam-standards.index'));

    $catalogSong = JamStandardSong::query()->sole();

    expect($catalogSong->band_template_id)->toBe($template->id)
        ->and($catalogSong->slots()->pluck('name')->all())->toBe(['bass', 'drums']);

    $this->actingAs($admin)
        ->put(route('jam-standards.update', $catalogSong), [
            'artist' => 'The Stooges',
            'title' => 'Search and Destroy',
            'slot_names' => ['vocals'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    expect($catalogSong->refresh()->band_template_id)->toBeNull()
        ->and($catalogSong->slots()->pluck('name')->all())->toBe(['vocals']);
});

test('the admin catalog add modal submits asynchronously', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $catalogSong = JamStandardSong::query()->create([
        'artist' => 'Talking Heads',
        'title' => 'Psycho Killer',
        'notes' => 'Keep the bass line steady.',
        'is_active' => true,
    ]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);

    $this->actingAs($admin)
        ->get(route('jam-standards.index'))
        ->assertOk()
        ->assertSee('x-ref="catalogRows"', false)
        ->assertSee('<table', false)
        ->assertSee('Keep the bass line steady.')
        ->assertSee('mt-1 text-xs text-slate-500', false)
        ->assertSee("window.dispatchEvent(new CustomEvent('open-catalog-add-song'))", false)
        ->assertSee('catalogPagination', false)
        ->assertSee('type="submit"', false)
        ->assertSee('Delete Song')
        ->assertSee('Song Coverage')
        ->assertSee('catalog-song-coverage', false)
        ->assertSee('Search by artist or song title, then filter by performers who know parts on those songs.')
        ->assertSee('text-slate-900 placeholder:text-slate-500', false)
        ->assertSee('catalog-song-form', false)
        ->assertSee('name="duration"', false)
        ->assertSee('name="source"', false)
        ->assertSee('catalogSelectedDeezerDuration', false)
        ->assertSee('Start typing an artist to fetch Deezer suggestions.')
        ->assertSee('Song suggestions are scoped to the selected artist.')
        ->assertSee('Looking up Deezer artists')
        ->assertSee('Looking up Deezer songs')
        ->assertSee('catalogArtistLookupBusy', false)
        ->assertSee('catalogTitleLookupBusy', false)
        ->assertSee('catalog-song-edit', false)
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('${editingSong?.id}`', false)
        ->assertSee('Start typing an artist to fetch Deezer suggestions.')
        ->assertSee('Song suggestions are scoped to the selected artist.')
        ->assertSee('catalog-quick-set', false)
        ->assertSee('quickSetJamSessionId', false)
        ->assertSee('quickSetName', false)
        ->assertSee('initialStatusMessage', false)
        ->assertSee('x-transition.opacity', false)
        ->assertSee('rounded-lg border border-emerald-200 bg-emerald-50', false)
        ->assertSee('performerNames', false)
        ->assertSee('selectedPerformerFilterLabel()', false)
        ->assertSee('@click="resetCatalogSearch()"', false)
        ->assertSee('quickSetSongs', false)
        ->assertSee('x-for="song in selectedQuickSetSongs()"', false)
        ->assertSee('name="requester_slot_names[]"', false)
        ->assertSee("checked ? 'cursor-pointer border-sky-300 bg-sky-50 text-sky-700'", false)
        ->assertSee('confirmQuickSetSubmission()', false)
        ->assertSee('Set Name <span class="text-rose-600"', false)
        ->assertSee('Jam Session <span class="text-rose-600"', false)
        ->assertSee('Select the parts you want to take on for each song.')
        ->assertSee('text-sky-500', false)
        ->assertSee('text-orange-500', false)
        ->assertSee('rounded-lg border border-slate-200 bg-slate-50 p-3', false)
        ->assertSee('border-sky-300 bg-sky-50 text-sky-700', false)
        ->assertSee("selected ? 'border-sky-300 bg-white text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-600'", false)
        ->assertSee('h-4 w-4', false)
        ->assertSee('catalogRowClass(selectedSongIds.includes(', false)
        ->assertSee('$el.querySelector(\'input\').click()', false)
        ->assertDontSee('Your capabilities')
        ->assertSee('Jam Standards')
        ->assertSee('The jam standards are a list of songs that many of our regulars know.')
        ->assertSee('Learning some of these is a great way to get started with our jam sessions.')
        ->assertSee('border border-sky-200 bg-sky-50/80', false)
        ->assertSee('flex items-center gap-2 text-sm leading-6 text-sky-900', false)
        ->assertSee('h-5 w-5 text-sky-500', false)
        ->assertSee('I can perform')
        ->assertSee('jamStandardsCatalog', false)
        ->assertSee('Band template')
        ->assertSee('Add Song');
});

test('catalog performer options include the current user', function () {
    $viewer = User::factory()->create();
    $performer = User::factory()->create();

    $response = $this->actingAs($viewer)->get(route('jam-standards.index'));

    expect($response->viewData('users')->pluck('id')->all())
        ->toContain($viewer->id)
        ->toContain($performer->id);
});

test('catalog selected songs persist across paginated fetches in the frontend component', function () {
    $component = file_get_contents(resource_path('js/components/jamStandardsCatalog.js'));

    expect($component)
        ->not->toContain('this.selectedSongIds = [];')
        ->toContain('selection.checked = this.selectedSongIds.includes(song.id);');
});

test('pending catalog requests are shown in their own panel above the catalog table', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $requester = User::factory()->create();
    JamStandardSong::query()->create(['artist' => 'Bauhaus', 'title' => 'Bela Lugosi\'s Dead', 'is_active' => true]);
    JamStandardSongRequest::query()->create([
        'requester_user_id' => $requester->id,
        'artist' => 'Portishead',
        'title' => 'Sour Times',
        'slot_names' => ['bass'],
        'requester_slot_names' => ['bass'],
    ]);

    $response = $this->actingAs($admin)->get(route('jam-standards.index'));

    $response
        ->assertOk()
        ->assertSee('Catalog Requests')
        ->assertSee('aria-label="Approve catalog request"', false)
        ->assertSee('aria-label="Reject catalog request"', false)
        ->assertSee('h-8 w-8 items-center justify-center rounded-md text-emerald-700', false)
        ->assertSee('h-8 w-8 items-center justify-center rounded-md text-rose-700', false)
        ->assertSee('data-catalog-requests', false)
        ->assertSee('mt-3 space-y-3', false)
        ->assertSee('rounded-lg border border-slate-200 bg-white/90 p-4 shadow-sm', false)
        ->assertSee('Portishead - Sour Times');

    expect(strpos($response->getContent(), 'Catalog Requests'))
        ->toBeLessThan(strpos($response->getContent(), '<table'));
});

test('a user sees all pending catalog requests but can cancel only their own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownRequest = JamStandardSongRequest::query()->create([
        'requester_user_id' => $user->id,
        'artist' => 'The Cure',
        'title' => 'A Forest',
        'slot_names' => ['bass'],
    ]);
    $otherRequest = JamStandardSongRequest::query()->create([
        'requester_user_id' => $otherUser->id,
        'artist' => 'Cocteau Twins',
        'title' => 'Heaven or Las Vegas',
        'slot_names' => ['bass'],
    ]);

    $this->actingAs($user)
        ->get(route('jam-standards.index'))
        ->assertOk()
        ->assertSee('Catalog Requests')
        ->assertSee('The Cure - A Forest')
        ->assertSee('requested by you')
        ->assertSee('Cocteau Twins - Heaven or Las Vegas')
        ->assertSee('requested by '.$otherUser->name)
        ->assertSee('Cancel')
        ->assertDontSee('aria-label="Approve catalog request"', false)
        ->assertDontSee('aria-label="Reject catalog request"', false);

    $this->actingAs($user)
        ->deleteJson(route('jam-standards.requests.destroy', $ownRequest))
        ->assertOk()
        ->assertJsonPath('request_id', $ownRequest->id)
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('remaining_request_count', 1);

    expect(JamStandardSongRequest::query()->find($ownRequest->id))->toBeNull()
        ->and(JamStandardSongRequest::query()->find($otherRequest->id))->not->toBeNull();
});

test('a user cannot cancel another users catalog request', function () {
    $user = User::factory()->create();
    $requester = User::factory()->create();
    $catalogRequest = JamStandardSongRequest::query()->create([
        'requester_user_id' => $requester->id,
        'artist' => 'Siouxsie and the Banshees',
        'title' => 'Spellbound',
        'slot_names' => ['bass'],
    ]);

    $this->actingAs($user)
        ->delete(route('jam-standards.requests.destroy', $catalogRequest))
        ->assertForbidden();

    expect($catalogRequest->exists)->toBeTrue();
});

test('an admin can update a catalog song and replace its slots', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Joy Division', 'title' => 'Transmission', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $catalogSong->id, 'user_id' => $admin->id, 'slot_name' => 'bass']);

    $this->actingAs($admin)
        ->put(route('jam-standards.update', $catalogSong), [
            'artist' => 'New Order',
            'title' => 'Transmission',
            'notes' => 'Updated catalog details',
            'slot_names' => ['drums'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    expect($catalogSong->refresh()->artist)->toBe('New Order')
        ->and($catalogSong->notes)->toBe('Updated catalog details')
        ->and($catalogSong->slots()->pluck('name')->all())->toBe(['drums'])
        ->and($catalogSong->userSlots()->count())->toBe(0);
});

test('an admin can update a catalog song asynchronously', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Portishead', 'title' => 'Roads', 'is_active' => true]);

    $this->actingAs($admin)
        ->putJson(route('jam-standards.update', $catalogSong), [
            'artist' => 'Portishead',
            'title' => 'Roads (Live)',
            'slot_names' => ['vocals'],
        ])
        ->assertOk()
        ->assertJsonPath('song.title', 'Roads (Live)')
        ->assertJsonPath('song.slots.0.name', 'vocals');
});

test('an admin can delete a catalog song without deleting linked set songs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $performer = User::factory()->create();
    $jamSession = JamSession::query()->create(['name' => 'Catalog Deletion Jam', 'date' => now()->addDay(), 'description' => null]);
    $set = Set::query()->create(['owner_id' => $admin->id, 'jam_session_id' => $jamSession->id, 'name' => 'Linked Set']);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'David Bowie', 'title' => 'Heroes', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $catalogSong->id, 'user_id' => $performer->id, 'slot_name' => 'bass']);
    $linkedSong = Song::query()->create([
        'set_id' => $set->id,
        'jam_standard_song_id' => $catalogSong->id,
        'artist' => 'David Bowie',
        'title' => 'Heroes',
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('jam-standards.destroy', $catalogSong))
        ->assertOk()
        ->assertJsonPath('deleted_id', $catalogSong->id);

    $this->assertDatabaseMissing('jam_standard_songs', ['id' => $catalogSong->id]);
    $this->assertDatabaseMissing('jam_standard_slots', ['jam_standard_song_id' => $catalogSong->id]);
    $this->assertDatabaseMissing('jam_standard_user_slots', ['jam_standard_song_id' => $catalogSong->id]);
    expect($linkedSong->fresh()->jam_standard_song_id)->toBeNull();
});

test('an admin can view catalog song coverage grouped by performer', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $firstPerformer = User::factory()->create(['name' => 'Alex Performer']);
    $secondPerformer = User::factory()->create(['name' => 'Sam Performer']);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'The Cure', 'title' => 'Just Like Heaven', 'is_active' => true]);
    collect([
        ['jam_standard_song_id' => $catalogSong->id, 'user_id' => $firstPerformer->id, 'slot_name' => 'bass'],
        ['jam_standard_song_id' => $catalogSong->id, 'user_id' => $firstPerformer->id, 'slot_name' => 'vocals'],
        ['jam_standard_song_id' => $catalogSong->id, 'user_id' => $secondPerformer->id, 'slot_name' => 'drums'],
    ])->each(fn (array $attributes) => JamStandardUserSlot::query()->create($attributes));

    $this->actingAs($admin)
        ->getJson(route('jam-standards.coverage', $catalogSong))
        ->assertOk()
        ->assertJsonPath('song.title', 'Just Like Heaven')
        ->assertJsonPath('coverage.0.name', 'Alex Performer')
        ->assertJsonPath('coverage.0.slot_names', ['bass', 'vocals'])
        ->assertJsonPath('coverage.1.name', 'Sam Performer')
        ->assertJsonPath('coverage.1.slot_names', ['drums']);
});

test('users can save song-specific catalog slot capabilities', function () {
    $user = User::factory()->create(['last_seen_at' => now()]);
    $catalogSong = JamStandardSong::query()->create([
        'artist' => 'The Clash',
        'title' => 'Should I Stay or Should I Go',
        'is_active' => true,
    ]);
    $catalogSong->slots()->createMany([
        ['name' => 'vocals', 'position' => 1],
        ['name' => 'bass', 'position' => 2],
    ]);

    $this->actingAs($user)
        ->putJson(route('jam-standards.capabilities.update', $catalogSong), [
            'slot_names' => ['bass'],
        ])
        ->assertOk()
        ->assertJson(['slot_names' => ['bass']])
        ->assertJsonPath('slot_capability_counts.bass', 1);

    expect(JamStandardUserSlot::query()->where('user_id', $user->id)->pluck('slot_name')->all())->toBe(['bass']);

    $this->actingAs($user)
        ->post(route('jam-standards.capabilities.update', $catalogSong), [
            '_method' => 'PUT',
            'slot_names' => [],
        ])
        ->assertOk()
        ->assertJsonPath('slot_capability_counts.bass', 0);

    expect(JamStandardUserSlot::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('catalog slot counts include only recently seen users with song-specific capabilities', function () {
    $viewer = User::factory()->create(['last_seen_at' => now()]);
    $recentPerformer = User::factory()->create(['last_seen_at' => now()->subMonths(5)]);
    $inactivePerformer = User::factory()->create(['last_seen_at' => now()->subMonths(6)->subSecond()]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Fleetwood Mac', 'title' => 'Dreams', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $catalogSong->id, 'user_id' => $recentPerformer->id, 'slot_name' => 'bass']);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $catalogSong->id, 'user_id' => $inactivePerformer->id, 'slot_name' => 'bass']);

    $this->actingAs($viewer)
        ->getJson(route('jam-standards.index'))
        ->assertOk()
        ->assertJsonPath('songs.0.slots.0.name', 'bass')
        ->assertJsonPath('songs.0.slots.0.recent_capability_count', 1);
});

test('catalog can be filtered by artist or capabilities shared by all selected performers', function () {
    $bassPlayer = User::factory()->create(['name' => 'Bass Player']);
    $drummer = User::factory()->create(['name' => 'Drummer']);
    $viewer = User::factory()->create();
    $matchingSong = JamStandardSong::query()->create(['artist' => 'Rage Against the Machine', 'title' => 'Killing in the Name', 'is_active' => true]);
    $otherSong = JamStandardSong::query()->create(['artist' => 'Radiohead', 'title' => 'Creep', 'is_active' => true]);
    $matchingSong->slots()->createMany([
        ['name' => 'bass', 'position' => 1],
        ['name' => 'drums', 'position' => 2],
    ]);
    $otherSong->slots()->create(['name' => 'drums', 'position' => 1]);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $matchingSong->id, 'user_id' => $bassPlayer->id, 'slot_name' => 'bass']);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $matchingSong->id, 'user_id' => $drummer->id, 'slot_name' => 'drums']);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $otherSong->id, 'user_id' => $drummer->id, 'slot_name' => 'drums']);
    JamStandardUserSlot::query()->create(['jam_standard_song_id' => $matchingSong->id, 'user_id' => $viewer->id, 'slot_name' => 'bass']);

    $this->actingAs($viewer)
        ->get(route('jam-standards.index', ['q' => 'Radiohead']))
        ->assertOk()
        ->assertSee('Radiohead - Creep')
        ->assertDontSee('Rage Against the Machine - Killing in the Name');

    $this->actingAs($viewer)
        ->get(route('jam-standards.index', ['user_ids' => [$bassPlayer->id, $drummer->id]]))
        ->assertOk()
        ->assertSee('Rage Against the Machine - Killing in the Name')
        ->assertSee('Bass Player, Drummer know these parts')
        ->assertSee('border-sky-300 bg-sky-50 text-sky-700', false)
        ->assertSee('border-emerald-300 bg-emerald-50 text-emerald-700', false)
        ->assertSee('Bass')
        ->assertDontSee('Radiohead - Creep');
});

test('catalog search returns filtered JSON for dynamic rendering', function () {
    $viewer = User::factory()->create();
    JamStandardSong::query()->create(['artist' => 'Radiohead', 'title' => 'Paranoid Android', 'is_active' => true]);
    JamStandardSong::query()->create(['artist' => 'Blur', 'title' => 'Song 2', 'is_active' => true]);

    $this->actingAs($viewer)
        ->getJson(route('jam-standards.index', ['q' => 'Radiohead']))
        ->assertOk()
        ->assertJsonCount(1, 'songs')
        ->assertJsonPath('songs.0.artist', 'Radiohead')
        ->assertJsonPath('songs.0.title', 'Paranoid Android');
});

test('catalog search filters dynamic results by capabilities shared by all selected performers', function () {
    $viewer = User::factory()->create();
    $bassPlayer = User::factory()->create(['name' => 'Bass Player']);
    $drummer = User::factory()->create(['name' => 'Drummer']);
    $matchingSong = JamStandardSong::query()->create(['artist' => 'Rage Against the Machine', 'title' => 'Killing in the Name', 'is_active' => true]);
    $otherSong = JamStandardSong::query()->create(['artist' => 'Radiohead', 'title' => 'Creep', 'is_active' => true]);
    $matchingSong->slots()->createMany([
        ['name' => 'bass', 'position' => 1],
        ['name' => 'drums', 'position' => 2],
    ]);
    $otherSong->slots()->create(['name' => 'drums', 'position' => 1]);
    JamStandardUserSlot::query()->create([
        'jam_standard_song_id' => $matchingSong->id,
        'user_id' => $bassPlayer->id,
        'slot_name' => 'bass',
    ]);
    JamStandardUserSlot::query()->create([
        'jam_standard_song_id' => $matchingSong->id,
        'user_id' => $drummer->id,
        'slot_name' => 'drums',
    ]);
    JamStandardUserSlot::query()->create([
        'jam_standard_song_id' => $otherSong->id,
        'user_id' => $drummer->id,
        'slot_name' => 'drums',
    ]);

    $this->actingAs($viewer)
        ->getJson(route('jam-standards.index', ['user_ids' => [$bassPlayer->id, $drummer->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'songs')
        ->assertJsonPath('songs.0.artist', 'Rage Against the Machine')
        ->assertJsonPath('songs.0.performer_slots.bass.0', $bassPlayer->id)
        ->assertJsonPath('songs.0.performer_slots.drums.0', $drummer->id)
        ->assertJsonPath('performers.0.name', 'Bass Player')
        ->assertJsonPath('performers.1.name', 'Drummer');
});

test('catalog performer filtering excludes users hidden from directory', function () {
    $viewer = User::factory()->create();
    $visiblePerformer = User::factory()->create(['name' => 'Visible Performer', 'hide_from_directory' => false]);
    $hiddenPerformer = User::factory()->create(['name' => 'Hidden Performer', 'hide_from_directory' => true]);

    $catalogSong = JamStandardSong::query()->create([
        'artist' => 'Soundgarden',
        'title' => 'Black Hole Sun',
        'is_active' => true,
    ]);
    $catalogSong->slots()->create(['name' => 'vocals', 'position' => 1]);

    JamStandardUserSlot::query()->create([
        'jam_standard_song_id' => $catalogSong->id,
        'user_id' => $visiblePerformer->id,
        'slot_name' => 'vocals',
    ]);

    JamStandardUserSlot::query()->create([
        'jam_standard_song_id' => $catalogSong->id,
        'user_id' => $hiddenPerformer->id,
        'slot_name' => 'vocals',
    ]);

    $this->actingAs($viewer)
        ->get(route('jam-standards.index'))
        ->assertOk()
        ->assertSee('Visible Performer')
        ->assertDontSee('Hidden Performer');

    $this->actingAs($viewer)
        ->getJson(route('jam-standards.index', ['user_ids' => [$visiblePerformer->id, $hiddenPerformer->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'performers')
        ->assertJsonPath('performers.0.name', 'Visible Performer');
});

test('catalog performer filter includes the current user for completeness', function () {
    $viewer = User::factory()->create([
        'name' => 'Current Viewer',
        'hide_from_directory' => true,
    ]);
    $otherHiddenPerformer = User::factory()->create([
        'name' => 'Other Hidden Performer',
        'hide_from_directory' => true,
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('jam-standards.index'))
        ->assertOk();

    /** @var Collection<int, User> $users */
    $users = $response->viewData('users');

    expect($users->contains(fn (User $user) => $user->id === $viewer->id))->toBeTrue();
    expect($users->contains(fn (User $user) => $user->id === $otherHiddenPerformer->id))->toBeFalse();
});

test('catalog pagination is included in normal and dynamic search results', function () {
    $viewer = User::factory()->create();

    collect(range(1, 16))->each(function (int $number): void {
        JamStandardSong::query()->create([
            'artist' => 'Pagination Artist',
            'title' => 'Catalog Song '.$number,
            'is_active' => true,
        ]);
    });

    $this->actingAs($viewer)
        ->get(route('jam-standards.index'))
        ->assertOk()
        ->assertSee('Page 1 of 2')
        ->assertSee('16 songs');

    $this->actingAs($viewer)
        ->getJson(route('jam-standards.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'songs')
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('pagination.total', 16);
});

test('a user can request a catalog song and an admin approval adds it with requester capabilities', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'Alice in Chains',
            'title' => 'Would?',
            'duration' => 207,
            'source' => 'deezer',
            'slot_names' => ['bass', 'vocals'],
            'requester_slot_names' => ['bass'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    $catalogRequest = JamStandardSongRequest::query()->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('jam-standards.requests.respond', $catalogRequest), ['status' => 'approved'])
        ->assertRedirect(route('jam-standards.index'));

    $catalogSong = JamStandardSong::query()->where('title', 'Would?')->firstOrFail();

    expect($catalogRequest->refresh()->status)->toBe(JamStandardSongRequest::STATUS_APPROVED)
        ->and($catalogSong->duration)->toBe(207)
        ->and($catalogSong->source)->toBe('deezer')
        ->and($catalogSong->slots()->pluck('name')->all())->toBe(['bass', 'vocals'])
        ->and($catalogSong->userSlots()->where('user_id', $user->id)->pluck('slot_name')->all())->toBe(['bass']);
});

test('a manual catalog request backfills a matching Deezer duration', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://api.deezer.com/search*' => Http::response([
            'data' => [[
                'title' => 'Disorder',
                'duration' => 209,
                'artist' => ['name' => 'Joy Division'],
            ]],
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'Joy Division',
            'title' => 'Disorder',
            'slot_names' => ['bass'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    Http::assertSentCount(1);

    expect(JamStandardSongRequest::query()->sole())
        ->duration->toBe(209)
        ->source->toBe('deezer');
});

test('a catalog request preserves an explicitly selected Deezer duration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'Joy Division',
            'title' => 'Disorder',
            'duration' => 208,
            'source' => 'deezer',
            'slot_names' => ['bass'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    expect(JamStandardSongRequest::query()->sole())
        ->duration->toBe(208)
        ->source->toBe('deezer');

    Http::assertNothingSent();
});

test('an approved catalog request retains its selected template', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $template = BandTemplate::query()->create(['name' => 'Synth Pop']);
    $template->slots()->create(['name' => 'keys']);
    $template->slots()->create(['name' => 'vocals']);

    $this->actingAs($user)
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'New Order',
            'title' => 'Blue Monday',
            'band_template_id' => $template->id,
            'requester_slot_names' => ['keys'],
        ])
        ->assertRedirect(route('jam-standards.index'));

    $catalogRequest = JamStandardSongRequest::query()->sole();

    $this->actingAs($admin)
        ->patch(route('jam-standards.requests.respond', $catalogRequest), ['status' => 'approved'])
        ->assertRedirect(route('jam-standards.index'));

    $catalogSong = JamStandardSong::query()->sole();

    expect($catalogSong->band_template_id)->toBe($template->id)
        ->and($catalogSong->slots()->pluck('name')->all())->toBe(['keys', 'vocals']);
});

test('catalog requests and moderation return JSON for dynamic page updates', function () {
    $user = User::factory()->create(['name' => 'Requesting Player']);
    $admin = User::factory()->create(['is_admin' => true]);

    $requestResponse = $this->actingAs($user)
        ->postJson(route('jam-standards.requests.store'), [
            'artist' => 'Massive Attack',
            'title' => 'Teardrop',
            'slot_names' => ['vocals', 'drums'],
            'requester_slot_names' => ['vocals'],
        ])
        ->assertCreated()
        ->assertJsonPath('request.requester.name', 'Requesting Player')
        ->assertJsonPath('request.artist', 'Massive Attack')
        ->assertJsonPath('request.title', 'Teardrop');

    $catalogRequest = JamStandardSongRequest::query()->firstOrFail();

    $this->actingAs($admin)
        ->patchJson(route('jam-standards.requests.respond', $catalogRequest), ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('request_id', $catalogRequest->id)
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('song.title', 'Teardrop')
        ->assertJsonPath('remaining_request_count', 0);
});

test('an admin can reject a catalog request asynchronously', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $catalogRequest = JamStandardSongRequest::query()->create([
        'requester_user_id' => $user->id,
        'artist' => 'Talking Heads',
        'title' => 'Psycho Killer',
        'slot_names' => ['bass'],
        'requester_slot_names' => ['bass'],
    ]);

    $this->actingAs($admin)
        ->patchJson(route('jam-standards.requests.respond', $catalogRequest), ['status' => 'rejected'])
        ->assertOk()
        ->assertJsonPath('request_id', $catalogRequest->id)
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('song', null);

    expect($catalogRequest->refresh()->status)->toBe(JamStandardSongRequest::STATUS_REJECTED);
});

test('a song request cannot claim a requester capability outside the requested song slots', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('jam-standards.index'))
        ->post(route('jam-standards.requests.store'), [
            'artist' => 'Sonic Youth',
            'title' => 'Teen Age Riot',
            'slot_names' => ['bass'],
            'requester_slot_names' => ['drums'],
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHasErrors('requester_slot_names');
});

test('a user can create a quick set in a chosen session with hidden and free-for-all flags', function () {
    $user = User::factory()->create();

    $jamSession = JamSession::query()->create([
        'name' => 'Quick Set Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
    ]);

    $catalogA = JamStandardSong::query()->create([
        'artist' => 'Thin Lizzy',
        'title' => 'The Boys Are Back in Town',
        'duration' => 267,
        'source' => 'deezer',
        'is_active' => true,
    ]);

    $catalogB = JamStandardSong::query()->create([
        'artist' => 'Rush',
        'title' => 'Tom Sawyer',
        'is_active' => true,
    ]);
    $catalogA->slots()->createMany([
        ['name' => 'vocals', 'position' => 1],
        ['name' => 'lead_guitar', 'position' => 2],
    ]);

    $response = $this->actingAs($user)
        ->post(route('jam-standards.quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'User Quick Set',
            'is_hidden' => '1',
            'free_for_all' => '1',
            'catalog_song_ids' => [$catalogA->id, $catalogB->id],
            'song_slots' => [
                $catalogA->id => ['vocals', 'lead_guitar'],
            ],
        ]);

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->toStartWith(route('sessions.show', $jamSession));

    $set = Set::query()->where('name', 'User Quick Set')->firstOrFail();

    expect($set->owner_id)->toBe($user->id)
        ->and($set->jam_session_id)->toBe($jamSession->id)
        ->and($set->is_hidden)->toBeTrue()
        ->and($set->free_for_all)->toBeTrue();

    $songs = Song::query()->where('set_id', $set->id)->orderBy('position')->get();

    expect($songs)->toHaveCount(2)
        ->and($songs->pluck('jam_standard_song_id')->all())->toBe([$catalogA->id, $catalogB->id]);

    expect($songs->first())
        ->duration->toBe(267)
        ->source->toBe('deezer');

    $assignedSlots = $songs->first()->slots()->orderBy('position')->get();

    expect($assignedSlots->pluck('name')->all())->toBe(['vocals', 'lead_guitar'])
        ->and($assignedSlots->pluck('user_id')->unique()->values()->all())->toBe([$user->id])
        ->and($user->fresh()->slotCoverageState('vocals'))->toBe(User::SLOT_COVERAGE_CAN)
        ->and($user->fresh()->slotCoverageState('lead_guitar'))->toBe(User::SLOT_COVERAGE_CAN);
});

test('quick sets only accept slots defined by each catalog song', function () {
    $user = User::factory()->create();
    $jamSession = JamSession::query()->create(['name' => 'Catalog Validation Jam', 'date' => now()->addDay(), 'description' => null]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'The Strokes', 'title' => 'Last Nite', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);

    $this->actingAs($user)
        ->from(route('jam-standards.index'))
        ->post(route('jam-standards.quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Invalid Quick Set',
            'catalog_song_ids' => [$catalogSong->id],
            'song_slots' => [$catalogSong->id => ['drums']],
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHasErrors('song_slots.'.$catalogSong->id);
});

test('a quick set creates every catalog slot while assigning only the selected user roles', function () {
    $user = User::factory()->create();
    $jamSession = JamSession::query()->create(['name' => 'Full Catalog Slots Jam', 'date' => now()->addDay(), 'description' => null]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Fleetwood Mac', 'title' => 'Dreams', 'is_active' => true]);
    $catalogSong->slots()->createMany([
        ['name' => 'vocals', 'position' => 1],
        ['name' => 'bass', 'position' => 2],
        ['name' => 'drums', 'position' => 3],
    ]);

    $this->actingAs($user)
        ->post(route('jam-standards.quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Full Slots Quick Set',
            'catalog_song_ids' => [$catalogSong->id],
            'song_slots' => [$catalogSong->id => ['bass']],
        ])
        ->assertRedirect();

    $song = Song::query()->where('set_id', Set::query()->where('name', 'Full Slots Quick Set')->value('id'))->firstOrFail();

    expect($song->slots()->orderBy('position')->pluck('name')->all())->toBe(['vocals', 'bass', 'drums'])
        ->and($song->slots()->where('name', 'bass')->value('user_id'))->toBe($user->id)
        ->and($song->slots()->where('name', 'vocals')->value('user_id'))->toBeNull()
        ->and($song->slots()->where('name', 'drums')->value('user_id'))->toBeNull();
});

test('a quick set cannot assign the creator to incompatible catalog slots', function () {
    $user = User::factory()->create();
    $jamSession = JamSession::query()->create(['name' => 'Conflict Quick Set Jam', 'date' => now()->addDay(), 'description' => null]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'The Police', 'title' => 'Message in a Bottle', 'is_active' => true]);
    $catalogSong->slots()->createMany([
        ['name' => 'bass', 'position' => 1],
        ['name' => 'lead_guitar', 'position' => 2],
    ]);

    $this->actingAs($user)
        ->from(route('jam-standards.index'))
        ->post(route('jam-standards.quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Conflict Quick Set',
            'catalog_song_ids' => [$catalogSong->id],
            'song_slots' => [$catalogSong->id => ['bass', 'lead_guitar']],
        ])
        ->assertRedirect(route('jam-standards.index'))
        ->assertSessionHasErrors('song_slots.'.$catalogSong->id);

    expect(Set::query()->where('name', 'Conflict Quick Set')->exists())->toBeFalse();
});

test('an admin can create a live quick set with a frequency-sorted artist name and partial assignments', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $performer = User::factory()->create();

    $jamSession = JamSession::query()->create([
        'name' => 'Live Quick Set Session',
        'date' => now()->addDays(1),
        'description' => null,
        'is_closed' => false,
        'is_live' => true,
        'jam_manager_id' => $admin->id,
    ]);

    $catalogSong = JamStandardSong::query()->create([
        'artist' => 'Nirvana',
        'title' => 'Smells Like Teen Spirit',
        'duration' => 301,
        'source' => 'deezer',
        'is_active' => true,
    ]);
    $catalogSong->slots()->createMany([
        ['name' => 'vocals', 'position' => 1],
        ['name' => 'bass', 'position' => 2],
    ]);
    $secondBlackSabbathSong = JamStandardSong::query()->create(['artist' => 'Black Sabbath', 'title' => 'Paranoid', 'is_active' => true]);
    $thirdBlackSabbathSong = JamStandardSong::query()->create(['artist' => 'Black Sabbath', 'title' => 'Iron Man', 'is_active' => true]);
    $kamelotSong = JamStandardSong::query()->create(['artist' => 'Kamelot', 'title' => 'March of Mephisto', 'is_active' => true]);
    $catalogSong->update(['artist' => 'Black Sabbath']);
    JamSessionSignIn::query()->create(['jam_session_id' => $jamSession->id, 'user_id' => $performer->id, 'signed_in_at' => now()]);

    $response = $this->actingAs($admin)
        ->post(route('jam-standards.live-quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'catalog_song_ids' => [$catalogSong->id, $secondBlackSabbathSong->id, $thirdBlackSabbathSong->id, $kamelotSong->id],
            'live_song_assignments' => [
                $catalogSong->id => [
                    'vocals' => $performer->id,
                ],
            ],
        ]);

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->toStartWith(route('sessions.show', $jamSession));

    $set = Set::query()->where('name', 'Black Sabbath/Kamelot')->firstOrFail();
    $song = Song::query()->where('set_id', $set->id)->firstOrFail();

    $vocals = $song->slots()->where('name', 'vocals')->first();
    $bass = $song->slots()->where('name', 'bass')->first();

    expect($vocals)->not()->toBeNull()
        ->and($bass)->not()->toBeNull()
        ->and($vocals->user_id)->toBe($performer->id)
        ->and($bass->user_id)->toBeNull()
        ->and($song->duration)->toBe(301)
        ->and($song->source)->toBe('deezer');
});

test('a live quick set returns its created set for an asynchronous request', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $jamSession = JamSession::query()->create(['name' => 'Async Live Quick Set', 'date' => now()->addDay(), 'description' => null, 'is_live' => true, 'jam_manager_id' => $admin->id]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Radiohead', 'title' => 'Creep', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'vocals', 'position' => 1]);

    $this->actingAs($admin)
        ->postJson(route('jam-standards.live-quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'catalog_song_ids' => [$catalogSong->id],
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Live quick set created from Jam Standards.')
        ->assertJsonPath('set.name', 'Radiohead');
});

test('a live quick set cannot assign one performer to incompatible slots for one song', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $performer = User::factory()->create();
    $jamSession = JamSession::query()->create([
        'name' => 'Live Conflict Quick Set Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
        'jam_manager_id' => $admin->id,
    ]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'The Police', 'title' => 'Message in a Bottle', 'is_active' => true]);
    $catalogSong->slots()->createMany([
        ['name' => 'bass', 'position' => 1],
        ['name' => 'lead_guitar', 'position' => 2],
    ]);
    JamSessionSignIn::query()->create(['jam_session_id' => $jamSession->id, 'user_id' => $performer->id, 'signed_in_at' => now()]);

    $this->actingAs($admin)
        ->from(route('sessions.live.manage', $jamSession))
        ->post(route('jam-standards.live-quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Live Conflict Quick Set',
            'catalog_song_ids' => [$catalogSong->id],
            'live_song_assignments' => [$catalogSong->id => ['bass' => $performer->id, 'lead_guitar' => $performer->id]],
        ])
        ->assertRedirect(route('sessions.live.manage', $jamSession))
        ->assertSessionHasErrors('live_song_assignments.'.$catalogSong->id.'.bass');

    expect(Set::query()->where('name', 'Live Conflict Quick Set')->exists())->toBeFalse();
});

test('only the active jam manager can create a live quick set', function () {
    $manager = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $jamSession = JamSession::query()->create(['name' => 'Managed Live Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true, 'jam_manager_id' => $manager->id]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'Blur', 'title' => 'Song 2', 'is_active' => true]);

    $this->actingAs($otherAdmin)
        ->post(route('jam-standards.live-quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Unauthorized Set',
            'catalog_song_ids' => [$catalogSong->id],
        ])
        ->assertForbidden();
});

test('live quick sets cannot assign an unchecked-in or unavailable user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $unavailable = User::factory()->create(['slot_coverage' => ['bass' => User::SLOT_COVERAGE_WONT]]);
    $jamSession = JamSession::query()->create(['name' => 'Attendee Validation Jam', 'date' => now()->addDay(), 'description' => null, 'is_live' => true, 'jam_manager_id' => $admin->id]);
    JamSessionSignIn::query()->create(['jam_session_id' => $jamSession->id, 'user_id' => $unavailable->id, 'signed_in_at' => now()]);
    $catalogSong = JamStandardSong::query()->create(['artist' => 'The White Stripes', 'title' => 'Seven Nation Army', 'is_active' => true]);
    $catalogSong->slots()->create(['name' => 'bass', 'position' => 1]);

    $this->actingAs($admin)
        ->from(route('sessions.live.manage', $jamSession))
        ->post(route('jam-standards.live-quick-set.store'), [
            'jam_session_id' => $jamSession->id,
            'set_name' => 'Unavailable Assignment',
            'catalog_song_ids' => [$catalogSong->id],
            'live_song_slots' => [$catalogSong->id => ['bass']],
            'live_song_assignments' => [$catalogSong->id => ['bass' => $unavailable->id]],
        ])
        ->assertRedirect(route('sessions.live.manage', $jamSession))
        ->assertSessionHasErrors('live_song_assignments.'.$catalogSong->id.'.bass');
});
