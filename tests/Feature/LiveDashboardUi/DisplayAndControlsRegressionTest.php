<?php

test('who is here modal uses fixed controls and attendee operation animations', function () {
    $modal = regressionResource('views/components/sessions/who-is-here-modal.blade.php');
    $css = regressionCss('app.css');

    expectContainsAll($modal, [
        'max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden',
        'min-h-0 flex-1 overflow-y-auto px-6 py-4',
        'flex shrink-0 flex-col gap-3 border-t border-slate-200',
        'value="Sign someone in"',
        '<div class="h-px w-full shrink-0 bg-slate-200 sm:h-10 sm:w-px" aria-hidden="true"></div>',
        'checkedInUserIds: new Set(),',
        'checkedOutUserIds: new Set(),',
        'this.attendees = (payload.attendees || []).filter(attendee => !this.checkedOutUserIds.has(String(attendee.id)));',
        'this.checkedOutUserIds.delete(String(attendee.id));',
        "'who-is-here-check-in': checkedInUserIds.has(String(attendee.id))",
        "'who-is-here-check-out': checkedOutUserIds.has(String(attendee.id))",
    ]);

    expectNotContainsAny($modal, ['feedback: \'\',']);
    expectContainsAll($css, ['@keyframes who-is-here-check-in', '@keyframes who-is-here-check-out']);
});

test('live dashboard uses emerald slot pills without outer rings', function () {
    $view = regressionResource('views/sessions/live/dashboard.blade.php');
    $managementView = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($view, [
        'slotBadgeClasses(slot)',
        'allowCheckins: @js((bool) $session->allow_checkins),',
        'route(\'jam-register.session\', $session->jam_register_code)',
        'x-show="allowCheckins" x-cloak',
        'fixed bottom-5 left-5 z-30 hidden w-[min(16rem,calc((100vw-min(80rem,calc(100vw-2.5rem)))/2-1.25rem))]',
        'fixed bottom-5 right-5 z-30 hidden w-[min(16rem,calc((100vw-min(80rem,calc(100vw-2.5rem)))/2-1.25rem))]',
        'backdrop-blur 2xl:block',
        'isLive: config.isLive,',
        'this.isLive = Boolean(payload.is_live);',
        'this.allowCheckins = Boolean(payload.allow_checkins);',
        'hidden text-center text-xs uppercase tracking-wide text-slate-500 sm:block',
        'x-show="lastUpdated" x-cloak x-text="lastUpdated"',
        'this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();',
        'this.pollTimer = setInterval(() => this.fetchData(), 5000);',
        '<div x-show="!isLive" class="flex items-center justify-center py-16 sm:py-24">',
        '<div x-show="isLive">',
        'border-emerald-300 bg-emerald-900/80 text-emerald-50',
        'bg-emerald-950/60 text-emerald-300',
        'bg-slate-800 text-slate-500',
        'x-show="!song.completed"',
        '!song.completed && song.slots.length > 0',
        ":class=\"comingUpSets.length === 1 ? 'grid-cols-1' : 'sm:grid-cols-2'\"",
        'class="columns-1 gap-2 sm:columns-2 xl:columns-3"',
        'rounded-xl border border-amber-700 bg-amber-950 p-3',
        'mb-2 break-inside-avoid rounded-xl border border-slate-800',
        'x-show="!playingNow.songs_collapsed"',
        'set.songs.length > 0 && !set.songs_collapsed',
        'set.songs.length > 0 && set.songs_collapsed',
        'collapsedSetPerformers(set)',
        'collapsedSetPerformers(set).slice(0, 10)',
        'collapsedSetPerformers(set).slice(10)',
        "collapsedSetPerformers(set).length > 10 ? 'sm:grid-cols-2' : ''",
        'collapsedSetPerformers(playingNow)',
        'collapsedSetPerformers(playingNow).slice(0, 10)',
        'collapsedSetPerformers(playingNow).slice(10)',
        "collapsedSetPerformers(playingNow).length > 10 ? 'sm:grid-cols-2' : ''",
        'collapsedSetPerformers(set) {',
        'const existingPerformer = performersByName.get(performerKey);',
        'checked_in: Boolean(existingPerformer?.checked_in || slot.checked_in),',
        '.sort((firstPerformer, secondPerformer) => firstPerformer.name.localeCompare(secondPerformer.name));',
        '<x-checked-in-dot x-show="performer.checked_in" x-cloak />',
        '<x-heroicon-m-check x-show="song.completed" x-cloak class="h-4 w-4 shrink-0 text-emerald-400" aria-hidden="true" />',
    ]);

    expectNotContainsAny($view, ['ring-1 ring-emerald-400/80', 'ring-1 ring-emerald-800']);

    expect(substr_count($view, 'fixed bottom-5'))->toBe(2);
    expect(substr_count($view, 'aspect-square w-[calc(100%-1rem)]'))->toBe(2);
    expect(substr_count($view, 'text-[clamp(0.625rem,1.1vw,1rem)]'))->toBe(2);
    expect(strpos($view, 'fixed bottom-5 left-5 z-30 hidden w-[min(16rem,calc((100vw-min(80rem,calc(100vw-2.5rem)))/2-1.25rem))]'))->toBeGreaterThan(strpos($view, '</main>'));
    expect(strpos($view, '!song.completed && song.slots.length > 0'))->toBeLessThan(strpos($view, '!song.completed && song.slots.filter(sl => sl.filled).length > 0'));

    expectContainsAll($managementView, [
        '@click="toggleSongCompleted(song)"',
        '@disabled(! $session->allow_checkins)',
        '@click="if (! $el.disabled) { $dispatch(\'open-who-is-here\') }"',
        'disabled:cursor-not-allowed disabled:border-slate-800 disabled:bg-slate-900 disabled:text-slate-600 disabled:opacity-60',
        "'Sign-ins are disabled for this jam'",
        'x-show="canManageLiveJam"',
        'x-show="set.has_duration_estimate"',
        '<span x-text="set.total_slots"></span>&nbsp;slots filled',
        '@click="toggleSetSongs(set)"',
        '@click="togglePublicSetSongs(set)"',
        "x-show=\"set.songs.length > 0 && set.status !== 'finished' && set.status !== 'postponed'\"",
        'title="Condensed view"',
        'aria-label="Condensed view"',
        "x-show=\"set.status !== 'playing_now' && set.status !== 'finished' && set.status !== 'postponed'\"",
        "<div x-show=\"set.status !== 'finished' && set.status !== 'postponed'\" class=\"my-1 h-px w-8 bg-slate-700/80\"></div>",
        "? 'border-violet-600 bg-violet-950/70 text-violet-300 hover:border-violet-500 hover:bg-violet-900/70 hover:text-violet-100'",
        ": 'border-slate-700 bg-slate-900 text-slate-300 hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100'",
        '<x-heroicon-m-arrows-pointing-in class="h-4 w-4" aria-hidden="true" />',
        'togglePublicSetSongs(set) {',
        'class="block text-left text-xl font-semibold text-slate-100',
        'x-transition:enter-start="opacity-0 translate-x-2"',
        'x-transition:leave-end="opacity-0 translate-x-2"',
        "'opacity-50': song.completed,",
        "x-bind:disabled=\"!canManageLiveJam || set.status === 'finished' || song.completed\"",
        "'hover:ring-2 hover:ring-amber-400': canManageLiveJam && set.status !== 'finished' && !song.completed",
        'class="ml-auto inline-flex h-6 w-6 shrink-0',
        'toggleSongCompleted(song) {',
        'song.completed = !song.completed;',
    ]);

    expectNotContainsAny($managementView, [
        '>#[<span x-text="set.id"></span>]',
        'x-text="`[${set.status}:${set.order}]`"',
        '<x-heroicon-m-arrows-pointing-out',
        '<x-heroicon-m-eye-slash class="h-4 w-4" aria-hidden="true" />',
        'focus:ring-amber-400',
        "'border-emerald-500 bg-emerald-950/50'",
        "(canManageLiveJam ? ' hover:border-emerald-700 hover:text-emerald-300' : '')",
    ]);

    expect(strpos($managementView, 'title="Condensed view"'))->toBeLessThan(strpos($managementView, 'title="Postpone"'));
});

test('live management shows checked-in slots and does not animate management controls', function () {
    $view = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($view, [
        'x-show="slot.checked_in"',
        '<x-checked-in-dot x-show="slot.checked_in" x-cloak class="ml-1" />',
        'x-show="canManageLiveJam"',
    ]);

    expectNotContainsAny($view, ['>Checked in</span>']);
});

test('session controls compact to icons and live management saves automatically', function () {
    $sessionView = regressionResource('views/sessions/show.blade.php');
    $liveManagementView = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($sessionView, [
        '<x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />',
        'class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900',
        '<x-heroicon-m-share class="h-4 w-4" aria-hidden="true" />',
        '<x-secondary-button @click="openEditSessionModal()" title="Edit Session" aria-label="Edit Session" class="gap-1.5">',
        '<span class="hidden sm:inline">Edit Session</span>',
        '<span class="hidden sm:inline">Live Admin</span>',
        'title="Open Live Admin" aria-label="Open Live Admin"',
        '<x-live-status-icon size="h-4 w-4" title="Open Live Admin" />',
        '<x-primary-button @click="openSet = true" title="Create Set" aria-label="Create Set" class="gap-1.5">',
        '<span class="hidden sm:inline">Create Set</span>',
    ]);

    expectContainsAll($liveManagementView, [
        '<span class="hidden sm:inline">Reset</span>',
        '<span class="hidden sm:inline">Add Set</span>',
        'class="flex items-center justify-start gap-2 text-sm text-slate-300"',
        'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3',
        'flex shrink-0 flex-wrap items-center justify-end gap-2',
        'x-transition:enter-start="opacity-0 translate-x-4"',
        'x-transition:leave-end="opacity-0 translate-x-4"',
        'x-text="canManageLiveJam ? \'You are the Jam Manager\' : (jamManagerName ? `${jamManagerName} is the Jam Manager` : \'Nobody is the Jam Manager\')"',
        '<div class="h-8 w-px shrink-0 bg-slate-700" aria-hidden="true"></div>',
        'mb-6 rounded-xl border border-slate-700 bg-slate-900/85 p-4 text-slate-100 shadow-sm',
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-950 transition disabled:opacity-50',
        '@click="releaseManager()"',
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-amber-800 bg-amber-950/60 text-amber-300 transition hover:border-amber-600 hover:bg-amber-900/70 disabled:opacity-50',
        'border border-amber-800 bg-amber-950/60',
        '<x-heroicon-m-arrow-right-on-rectangle class="h-3 w-3" aria-hidden="true" />',
        '<x-heroicon-m-arrow-left-on-rectangle class="h-3 w-3" aria-hidden="true" />',
        'aria-label="Manage"',
        'aria-label="Release Manager"',
        'aria-label="Reset"',
        'aria-label="Add Set"',
        'scheduleSave() {',
        'this.saveTimer = setTimeout(() => this.saveState(), 500);',
        'if (this.saveBusy) {',
        'if (this.saveQueued) {',
        'const savedState = this.sets.map(set => this.stateSnapshot(set));',
        'this.originalSets = savedState;',
        'setTimeout(() => this.saveState(), 2000);',
        'x-show="saveBusy"',
        'title="Saving"',
        '<x-heroicon-m-arrow-path class="h-4 w-4 animate-spin" aria-hidden="true" />',
    ]);

    expectNotContainsAny($liveManagementView, ["x-text=\"saveError || 'Saving…'\"", 'aria-label="Update"']);

    expect(strpos($liveManagementView, 'You are the Jam Manager'))->toBeLessThan(strpos($liveManagementView, '@click="claimManager()"'));
    expect(strpos($liveManagementView, '@click="clearState()"'))->toBeLessThan(strpos($liveManagementView, '@click="claimManager()"'));
    expect(strpos($liveManagementView, 'aria-label="Manage"'))->toBeLessThan(strpos($liveManagementView, '<x-heroicon-m-arrow-left-on-rectangle class="h-3 w-3" aria-hidden="true" />'));
    expect(strpos($liveManagementView, 'aria-label="Release Manager"'))->toBeLessThan(strpos($liveManagementView, '<x-heroicon-m-arrow-right-on-rectangle class="h-3 w-3" aria-hidden="true" />'));
});
