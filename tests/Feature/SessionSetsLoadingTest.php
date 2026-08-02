<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('add and edit set hidden controls use the hidden set styling and visibility description', function () {
    $hiddenControlClass = 'border-sky-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 shadow-[inset_0_0_6px_rgb(125_211_252_/_0.45),inset_0_0_14px_rgb(186_230_253_/_0.35)]';

    foreach ([
        resource_path('views/sessions/show.blade.php'),
        resource_path('views/components/sessions/set-card.blade.php'),
    ] as $viewPath) {
        $view = file_get_contents($viewPath);

        expect($view)
            ->toContain($hiddenControlClass)
            ->toContain('Hide this set from other users.')
            ->toContain('Only collaborators and admins will see the set.')
            ->not->toContain('Hide this set from other users (admins can still see it).');
    }
});

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
        'song_requests' => true,
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

    $songRequest = SongRequest::query()->create([
        'set_id' => $set->id,
        'requester_user_id' => $owner->id,
        'artist' => 'Requested Artist',
        'title' => 'Requested Song',
        'notes' => null,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Lazy Song')
        ->assertSee('Bass')
        ->assertSee('x-show="openSong"', false)
        ->assertSee('x-show="openSetEdit"', false)
        ->assertSee('x-show="openCollaborators"', false)
        ->assertSee('x-show="openSummary"', false)
        ->assertSee('Song requests')
        ->assertSee('data-song-request-id="'.$songRequest->id.'"', false)
        ->assertSee('x-show="songRequestsPendingCount > 0"', false);
});

test('edit set modal keeps closed jam sessions disabled in session list', function () {
    $owner = User::factory()->create();

    $openSession = JamSession::query()->create([
        'name' => 'Editable Open Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $closedSession = JamSession::query()->create([
        'name' => 'Locked Session Option',
        'date' => now()->addWeeks(2)->toDateString(),
        'description' => null,
        'is_closed' => true,
    ]);

    $pastSession = JamSession::query()->create([
        'name' => 'Past Session Option',
        'date' => now()->subDay()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Set With Session Picker',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $openSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$openSession, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee($closedSession->name)
        ->assertSee('(Closed)')
        ->assertDontSee($pastSession->name);

    $modal = file_get_contents(resource_path('views/components/sessions/set-edit-modal.blade.php'));

    expect($modal)->toContain('! $isAdmin && $isClosedSessionOption');
});

test('admin edit set modal includes past sessions and enables closed sessions except archived sessions', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $openSession = JamSession::query()->create([
        'name' => 'Admin Open Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $closedPastSession = JamSession::query()->create([
        'name' => 'Admin Closed Past Session',
        'date' => now()->subDays(2)->toDateString(),
        'description' => null,
        'is_closed' => true,
        'is_archived' => false,
    ]);

    $archivedSession = JamSession::query()->create([
        'name' => 'Archived Session Option',
        'date' => now()->addDays(3)->toDateString(),
        'description' => null,
        'is_closed' => false,
        'is_archived' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Set With Session Picker',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $openSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets.body', [$openSession, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee($closedPastSession->name.' ('.$closedPastSession->date->format('M j, Y').') (Closed)')
        ->assertDontSee('value="'.$closedPastSession->id.'" disabled', false)
        ->assertDontSee($archivedSession->name);
});

test('set card bodies load through the viewport observer or an explicit expansion', function () {
    $script = file_get_contents(resource_path('js/components/sessionCards.js'));
    $shell = file_get_contents(resource_path('views/components/sessions/set-card-shell.blade.php'));

    expect($script)
        ->toContain("rootMargin: '320px 0px'")
        ->toContain('queueLazySetBody(rootElement, () => this.loadSetBody(rootElement));')
        ->toContain('isWithinLazySetBodyBuffer(rootElement)')
        ->not->toContain('syncLazySetCard');

    expect($shell)
        ->toContain('initLazySetCard($el)')
        ->toContain('@click.stop="setCollapsed = !setCollapsed; if (!setCollapsed) loadSetBody()"')
        ->toContain('x-show="!setCollapsed && !contentLoaded && !contentLoadError"')
        ->toContain('h-4 w-44 max-w-full rounded-full bg-slate-200/80')
        ->toContain('class="flex cursor-pointer items-start justify-between gap-3"')
        ->toContain('class="min-w-0 flex-1"')
        ->toContain('class="shrink-0" @click.stop')
        ->toContain('class="relative flex h-8 w-8 items-center justify-center"')
        ->toContain('class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-amber-400"')
        ->toContain('animate-pulse')
        ->toContain('x-show="contentLoading && !contentLoaded"')
        ->toContain('x-show="contentLoaded || contentLoadError"')
        ->toContain('x-transition:enter="transition ease-out duration-200"')
        ->toContain('x-transition:enter-start="-rotate-12 scale-75 opacity-0"')
        ->toContain('x-transition:enter-end="rotate-0 scale-100 opacity-100"')
        ->not->toContain('syncLazySetCard($el)');
});

test('lazy session sets endpoint renders slot rows with extracted sessionSlotRow wiring', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Extracted Slot Row Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Extracted Slot Row Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Extracted Artist',
        'title' => 'Extracted Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('sessionSlotRow(', false)
        ->assertSee('requestSlotUrl', false)
        ->assertSee('takeSlotUrl', false)
        ->assertSee('proposeSlotUrl', false)
        ->assertSee('releaseSlotUrl', false)
        ->assertSee('destroySlotUrl', false)
        ->assertSee('#slot-'.$slot->id, false)
        ->assertSee('mobile-slot-move', false);
});

test('lazy session sets endpoint renders mobile slot activity card for pending slot assignments', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Mobile Slot Activity Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Mobile Slot Activity Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Mobile Artist',
        'title' => 'Mobile Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
    ]);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Slot requests &amp; recommendations', false)
        ->assertSee('pendingSlotActivityCount', false)
        ->assertSee('md:hidden', false)
        ->assertSee('class="hidden"', false);
});

test('mobile song activity cards render a cancel control for a requester', function () {
    $view = file_get_contents(resource_path('views/components/sessions/song-card.blade.php'));

    expect($view)
        ->toContain('@if ($canCancel && ! $setLocked)')
        ->toContain('@click="respond(\'rejected\')"')
        ->toContain('aria-label="Cancel slot request"');
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

test('session page includes local set search and filtering controls without replacing static card layout', function () {
    $view = file_get_contents(resource_path('views/sessions/show.blade.php'));
    $lazyScript = file_get_contents(resource_path('js/components/lazySessionSets.js'));
    $setShell = file_get_contents(resource_path('views/components/sessions/set-card-shell.blade.php'));

    expect($view)
        ->toContain('lazySessionSets(')
        ->toContain('currentUserId: @js((string) auth()->id())')
        ->toContain('Search by set, owner, or song')
        ->toContain('x-model="filterQuery"')
        ->toContain('selectedFilterLabel()')
        ->toContain('x-model="selectedAttributeFilters"')
        ->toContain('Ownership')
        ->toContain('Status')
        ->toContain('Sign ups')
        ->toContain('Visibility and mode')
        ->toContain('Attachments')
        ->toContain('value="my_sets"')
        ->toContain('value="collaborating"')
        ->toContain('value="performing_on"')
        ->toContain('value="planned"')
        ->toContain('value="performed"')
        ->toContain('value="signups_open"')
        ->toContain('value="signups_closed"')
        ->toContain('value="hidden"')
        ->toContain('value="free_for_all"')
        ->toContain('value="has_attachments"')
        ->toContain('Searching set songs...')
        ->toContain('x-text="`${visibleSetCount} of ${totalSetCount} sets`"')
        ->toContain('@click="clearFilters()"')
        ->toContain('x-show="hasActiveFilters()"')
        ->toContain('No sets match your current filters.');

    expect($lazyScript)
        ->toContain('matchesFilters(setCard)')
        ->toContain('matchesNonTextFilters(setCard)')
        ->toContain('applyFilters()')
        ->toContain('clearFilters()')
        ->toContain('runSummarySearch(query)')
        ->toContain('summaryTextFromPayload(payload)')
        ->toContain('setCard.dataset.setParticipants')
        ->toContain('if (!this.matchesNonTextFilters(setCard))')
        ->toContain('selectedAttributeFilters: []')
        ->toContain('filterOptions: [')
        ->toContain("{ key: 'my_sets', label: 'My sets' }")
        ->toContain("{ key: 'collaborating', label: \"Sets I'm collaborating on\" }")
        ->toContain("{ key: 'performing_on', label: \"Set's I'm performing on\" }")
        ->toContain("{ key: 'signups_open', label: 'Sign ups open' }")
        ->toContain("{ key: 'signups_closed', label: 'Sign ups closed' }")
        ->toContain("case 'my_sets':")
        ->toContain("case 'collaborating':")
        ->toContain("case 'performing_on':")
        ->toContain("case 'signups_closed':")
        ->toContain("case 'has_attachments':")
        ->toContain('this.applyFilters();')
        ->toContain('data-set-open="true"]:not(.hidden) [data-session-song-card][data-song-open="true"]');

    expect($setShell)
        ->toContain('data-set-name="{{ str($set->name)->lower() }}"')
        ->toContain('data-set-owner-name="{{ str($set->owner->name)->lower() }}"')
        ->toContain('data-set-participants="{{ str($setParticipantSearchText)->lower() }}"')
        ->toContain('data-set-summary-url="{{ route(\'sets.summary\', $set) }}"')
        ->toContain('data-set-owner-id="{{ $set->owner_id }}"')
        ->toContain('data-set-collaborating="{{ $isCollaborator ? \'1\' : \'0\' }}"')
        ->toContain('data-set-performing="{{ $isPerformingOnSet ? \'1\' : \'0\' }}"')
        ->toContain('data-set-performed="{{ $set->performed ? \'1\' : \'0\' }}"')
        ->toContain('data-set-hidden="{{ $set->is_hidden ? \'1\' : \'0\' }}"')
        ->toContain('data-set-feature="{{ $set->feature_set ? \'1\' : \'0\' }}"')
        ->toContain('data-set-has-attachments="{{ ($set->attachments_count ?? 0) > 0 ? \'1\' : \'0\' }}"')
        ->toContain('x-init="setCollapsed = true; songRequestsCollapsed = false; initLazySetCard($el)"')
        ->not->toContain('localStorage.getItem(setKey)')
        ->not->toContain('localStorage.setItem(setKey');
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
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
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
        ->toContain('Bass');

    expect($response->json('songs.'.$secondSong->id.'.slots_html'))
        ->toContain('Vocals');

    $script = file_get_contents(resource_path('js/components/lazySessionSets.js'));
    $store = file_get_contents(resource_path('js/stores/approvals.js'));

    expect($script)
        ->toContain('hasOpenSongCard()')
        ->toContain('activitySongIds()')
        ->toContain('getBoundingClientRect()')
        ->toContain('bottom >= -viewportHeight && top <= viewportHeight * 2')
        ->toContain('refreshOpenSongCards()')
        ->toContain('patchOpenSongSlots')
        ->toContain('canBackgroundRefreshSets()')
        ->toContain('fragmentFocusApplied')
        ->toContain('window.location.hash')
        ->toContain('hasOpenSessionActionMenu()')
        ->toContain('data-session-action-menu')
        ->toContain('if (isBackground && transitions.length > 0)')
        ->toContain('externalApprovalTransitions')
        ->toContain('data-song-request-id');

    expect($store)
        ->toContain('useRefreshProvider')
        ->toContain('clearRefreshProvider');
});

test('hidden sets stay visible to the owner but hidden from other users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Hidden Set Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $hiddenSet = Set::query()->create([
        'name' => 'Hidden Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'is_hidden' => true,
        'feature_set' => true,
    ]);

    Song::query()->create([
        'set_id' => $hiddenSet->id,
        'artist' => 'Hidden Artist',
        'title' => 'Hidden Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Hidden Set')
        ->assertSee('border-sky-400 bg-amber-50/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]', false);

    $this->actingAs($other)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertDontSee('Hidden Set');
});

test('hidden sets are not counted for other users on the session list', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Hidden Count Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    Set::query()->create([
        'name' => 'Count Hidden Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'is_hidden' => true,
    ]);

    $ownerResponse = $this->actingAs($owner)
        ->get(route('sessions.index'))
        ->assertOk();

    expect($ownerResponse->viewData('sessions')->firstWhere('id', $session->id)->sets_count)->toBe(1);

    $otherResponse = $this->actingAs($other)
        ->get(route('sessions.index'))
        ->assertOk();

    expect($otherResponse->viewData('sessions')->firstWhere('id', $session->id)->sets_count)->toBe(0);
});

test('navigation shows the hidden session indicator to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $member = User::factory()->create();
    $session = JamSession::query()->create([
        'name' => 'Hidden Navigation Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_hidden' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee($session->name)
        ->assertSee('Jam session is hidden from non-admin users');

    $this->actingAs($member)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertDontSee($session->name);
});
