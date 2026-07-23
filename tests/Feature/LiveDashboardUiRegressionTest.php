<?php

test('management dashboard preserves local changes when polling', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));
    $dashboardView = file_get_contents(resource_path('views/sessions/live/dashboard.blade.php'));

    expect($view)->toContain('init() {');
    expect($view)->not->toContain('x-init="init()"');
    expect($dashboardView)->toContain('init() {');
    expect($dashboardView)->not->toContain('x-init="init()"');
    expect($view)->toContain('const localSetsById = new Map(this.sets.map(set => [String(set.id), set]));');
    expect($view)->toContain('const refreshedSets = serverSets.map((serverSet) => {');
    expect($view)->toContain('...serverSet,');
    expect($view)->toContain('const localOnlySets = this.sets.filter(set => !serverSetIds.has(String(set.id)));');
    expect($view)->toContain('this.sets = [...refreshedSets, ...localOnlySets];');
});

test('management dashboard highlights new sets until browsed and keeps the updated status panel', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($view)->toContain('x-init="observeSetCard($el, set)"');
    expect($view)->toContain(':class="setCardClasses(set)"');
    expect($css)->toContain('.live-set-unseen {');
    expect($css)->toContain('animation: live-set-unseen-pulse 1.6s ease-in-out infinite;');
    expect($css)->toContain('border-color: rgb(56 189 248 / 0.8) !important;');
    expect($css)->toContain('box-shadow: 0 0 24px rgb(56 189 248 / 0.44);');
    expect($css)->toContain('.live-set-unseen-exit {');
    expect($css)->toContain('animation: live-set-unseen-exit 450ms ease-out forwards;');
    expect($view)->toContain('seenSetIds: new Set(),');
    expect($view)->toContain('seenObserver: null,');
    expect($view)->toContain('seenDwellTimers: new Map(),');
    expect($view)->toContain('return `live-jam-seen-sets:${config.dataUrl}`;');
    expect($view)->toContain("this.seenSetIds = new Set(JSON.parse(localStorage.getItem(this.seenSetIdsKey()) || '[]'));");
    expect($view)->toContain('set.highlighted = !this.seenSetIds.has(String(set.id));');
    expect($view)->toContain('this.markSetSeen(set);');
    expect($view)->toContain('handleSetVisibility(entries) {');
    expect($view)->toContain('entry.intersectionRatio < 0.2');
    expect($view)->toContain('this.fadeSetHighlight(set);');
    expect($view)->toContain('}, 1200));');
    expect($view)->toContain('set.highlightFading = true;');
    expect($view)->toContain('set.highlightFading = false;');
    expect($view)->toContain('{ threshold: 0.2 },');
    expect($view)->toContain("stateClasses.push('live-set-unseen');");
    expect($view)->toContain("stateClasses.push('live-set-unseen-exit');");
    expect($view)->toContain('this.sets = serverSets.map(serverSet => this.applyHighlightIfNeeded({ ...serverSet }));');
    expect($view)->not->toContain('serverSets.forEach(set => this.seenSetIds.add(String(set.id)));');
    expect($view)->not->toContain('this.markSetSeen(newSet);');
    expect($view)->not->toContain('lastCheckedAt: null,');
    expect($view)->toContain('x-transition:enter-start="opacity-0 translate-x-4"');
    expect($view)->toContain('text-emerald-400');
    expect($view)->toContain('text-amber-400');
    expect($view)->toContain('text-slate-500');
    expect($view)->toContain('x-text="jamManagerName || \'No jam manager assigned yet\'"');
    expect($view)->toContain('h-6 w-6');
    expect($view)->toContain('class="flex flex-wrap items-center justify-center gap-2"');
    expect($view)->toContain('<x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />');
    expect($view)->not->toContain('Run of show');
    expect($view)->toContain('rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl');
    expect($view)->not->toContain('Last saved <span x-text="lastUpdated"></span>');
});

test('live dashboard uses emerald slot pills without outer rings', function () {
    $view = file_get_contents(resource_path('views/sessions/live/dashboard.blade.php'));
    $managementView = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('slotBadgeClasses(slot)');
    expect($view)->toContain('isLive: config.isLive,');
    expect($view)->toContain('this.isLive = Boolean(payload.is_live);');
    expect($view)->toContain('this.pollTimer = setInterval(() => this.fetchData(), 5000);');
    expect($view)->toContain('<div x-show="!isLive" class="flex items-center justify-center py-16 sm:py-24">');
    expect($view)->toContain('<div x-show="isLive">');
    expect($view)->toContain('border-emerald-300 bg-emerald-900/80 text-emerald-50');
    expect($view)->toContain('bg-emerald-950/60 text-emerald-300');
    expect($view)->toContain('bg-slate-800 text-slate-500');
    expect($view)->not->toContain('ring-1 ring-emerald-400/80');
    expect($view)->not->toContain('ring-1 ring-emerald-800');
    expect($view)->toContain('x-show="!song.completed"');
    expect($view)->toContain('!song.completed && song.slots.filter(sl => sl.filled).length > 0');
    expect($view)->toContain(":class=\"comingUpSets.length === 1 ? 'grid-cols-1' : 'sm:grid-cols-2'\"");
    expect($view)->toContain('x-show="!playingNow.songs_collapsed"');
    expect($view)->toContain('set.songs.length > 0 && !set.songs_collapsed');
    expect($view)->toContain('set.songs.length > 0 && set.songs_collapsed');
    expect($view)->toContain('collapsedSetPerformers(set)');
    expect($view)->toContain('collapsedSetPerformers(playingNow)');
    expect($view)->toContain('collapsedSetPerformers(set) {');
    expect($view)->toContain('performersByName.set(name.toLocaleLowerCase(), name);');
    expect($view)->toContain('.sort((firstName, secondName) => firstName.localeCompare(secondName));');
    expect($view)->toContain('<x-heroicon-m-check x-show="song.completed" x-cloak class="h-4 w-4 shrink-0 text-emerald-400" aria-hidden="true" />');
    expect($managementView)->toContain('@click="toggleSongCompleted(song)"');
    expect($managementView)->toContain('@disabled(! $session->allow_checkins)');
    expect($managementView)->toContain('@click="if (! $el.disabled) { $dispatch(\'open-who-is-here\') }"');
    expect($managementView)->toContain('disabled:cursor-not-allowed disabled:border-slate-800 disabled:bg-slate-900 disabled:text-slate-600 disabled:opacity-60');
    expect($managementView)->toContain("'Check-ins are disabled for this jam'");
    expect($managementView)->toContain('x-show="canManageLiveJam"');
    expect($managementView)->toContain('@click="toggleSetSongs(set)"');
    expect($managementView)->toContain('@click="togglePublicSetSongs(set)"');
    expect($managementView)->toContain("x-show=\"set.songs.length > 0 && set.status !== 'finished' && set.status !== 'postponed'\"");
    expect($managementView)->toContain('title="Condensed view"');
    expect($managementView)->toContain('aria-label="Condensed view"');
    expect(strpos($managementView, 'title="Condensed view"'))->toBeLessThan(strpos($managementView, 'title="Postpone"'));
    expect($managementView)->toContain("x-show=\"set.status !== 'playing_now' && set.status !== 'finished' && set.status !== 'postponed'\"");
    expect($managementView)->toContain("<div x-show=\"set.status !== 'finished' && set.status !== 'postponed'\" class=\"my-1 h-px w-8 bg-slate-700/80\"></div>");
    expect($managementView)->toContain("? 'border-violet-600 bg-violet-950/70 text-violet-300 hover:border-violet-500 hover:bg-violet-900/70 hover:text-violet-100'");
    expect($managementView)->toContain(": 'border-slate-700 bg-slate-900 text-slate-300 hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100'");
    expect($managementView)->toContain('<x-heroicon-m-arrows-pointing-in class="h-4 w-4" aria-hidden="true" />');
    expect($managementView)->not->toContain('<x-heroicon-m-arrows-pointing-out');
    expect($managementView)->not->toContain('<x-heroicon-m-eye-slash class="h-4 w-4" aria-hidden="true" />');
    expect($managementView)->toContain('togglePublicSetSongs(set) {');
    expect($managementView)->toContain('class="flex min-w-0 items-center gap-2 text-left text-xl font-semibold text-slate-100');
    expect($managementView)->not->toContain('focus:ring-amber-400');
    expect($managementView)->toContain('x-transition:enter-start="opacity-0 translate-x-2"');
    expect($managementView)->toContain('x-transition:leave-end="opacity-0 translate-x-2"');
    expect($managementView)->toContain("'opacity-50': song.completed,");
    expect($managementView)->not->toContain("'border-emerald-500 bg-emerald-950/50'");
    expect($managementView)->toContain("x-bind:disabled=\"!canManageLiveJam || set.status === 'finished' || song.completed\"");
    expect($managementView)->toContain("'hover:ring-2 hover:ring-amber-400': canManageLiveJam && set.status !== 'finished' && !song.completed");
    expect($managementView)->toContain('class="ml-auto inline-flex h-6 w-6 shrink-0');
    expect($managementView)->not->toContain("(canManageLiveJam ? ' hover:border-emerald-700 hover:text-emerald-300' : '')");
    expect($managementView)->toContain('toggleSongCompleted(song) {');
    expect($managementView)->toContain('song.completed = !song.completed;');
});

test('live management assignment badges open and save through the assignment editor', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));
    $slotEditModal = file_get_contents(resource_path('views/components/sessions/slot-edit-modal.blade.php'));
    $sessionCards = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($view)->toContain('@click="openEditSlotModal(set, song, slot)"');
    expect($view)->toContain('<template x-if="slot.manual_performer_name">');
    expect($view)->toContain('<x-heroicon-m-pencil-square class="h-3 w-3" aria-hidden="true" />');
    expect($view)->toContain('title="Manually assigned"');
    expect($view)->toContain('<x-sessions.slot-edit-modal :slot-options="$slotOptions" :users="$assignmentUsers" live-dashboard />');
    expect($view)->toContain('async submitLiveSlotEdit()');
    expect($view)->toContain("config.slotUpdateUrlTemplate.replace('__slot__', slot.id)");
    expect($slotEditModal)->toContain("'liveDashboard' => false");
    expect($slotEditModal)->toContain('x-show="openEditSlot"');
    expect($slotEditModal)->toContain('Assigned User or Manual Name');
    expect($slotEditModal)->toContain('Clear Slot');
    expect($slotEditModal)->toContain('x-show="assignmentConflictMessage"');
    expect($slotEditModal)->toContain('assignmentSaveBusy || assignmentConflictCooldown');
    expect($slotEditModal)->toContain('class="disabled:cursor-not-allowed disabled:opacity-40"');
    expect($slotEditModal)->not->toContain('Move assignment');
    expect($slotEditModal)->not->toContain('Reviewing...');
    expect($view)->toContain('assignmentConflictMessage: \'\',');
    expect($view)->toContain('showAssignmentConflict(message) {');
    expect($view)->toContain('Click Save to move the assignment.');
    expect($view)->toContain('replace_conflicting_assignment: this.assignmentConflictPending,');
    expect($view)->not->toContain('window.confirm(`${conflict.message} Continue?`)');
    expect($sessionCards)->toContain('showAssignmentConflict(message) {');
    expect($sessionCards)->toContain('Click Save to move the assignment.');
    expect($sessionCards)->toContain("formData.set('replace_conflicting_assignment', '1');");
    expect($sessionCards)->not->toContain('window.confirm(`${conflict.message} Continue?`)');
});

test('live management shows checked-in slots and does not animate management controls', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('x-show="slot.checked_in"');
    expect($view)->toContain('<x-checked-in-dot x-show="slot.checked_in" x-cloak class="ml-1" />');
    expect($view)->not->toContain('>Checked in</span>');
    expect($view)->toContain("x-show=\"canManageLiveJam\"\n                        x-cloak\n                        class=\"flex flex-wrap items-center justify-center gap-2\"");
});

test('session controls compact to icons and live management saves automatically', function () {
    $sessionView = file_get_contents(resource_path('views/sessions/show.blade.php'));
    $liveManagementView = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($sessionView)->toContain('<x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />');
    expect($sessionView)->toContain('class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900');
    expect($sessionView)->toContain('<x-heroicon-m-share class="h-4 w-4" aria-hidden="true" />');
    expect($sessionView)->toContain('<span class="hidden sm:inline">Edit Session</span>');
    expect($sessionView)->toContain('<span class="hidden sm:inline">Live Dashboard</span>');
    expect($sessionView)->toContain('<span class="hidden sm:inline">Create Set</span>');
    expect($liveManagementView)->toContain('<span class="hidden sm:inline">Reset</span>');
    expect($liveManagementView)->toContain('<span class="hidden sm:inline">Add Set</span>');
    expect($liveManagementView)->toContain('relative mb-6 rounded-xl border border-slate-700 bg-slate-900/85 p-4 text-slate-100 shadow-sm');
    expect($liveManagementView)->toContain('absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-950');
    expect($liveManagementView)->toContain('@click="releaseManager()"');
    expect($liveManagementView)->toContain('absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-800');
    expect($liveManagementView)->toContain('border border-amber-800 bg-amber-950/60');
    expect($liveManagementView)->toContain('<x-heroicon-m-microphone class="h-3 w-3" aria-hidden="true" />');
    expect($liveManagementView)->toContain('<x-heroicon-m-arrow-left-on-rectangle class="h-3 w-3" aria-hidden="true" />');
    expect($liveManagementView)->toContain('aria-label="Manage"');
    expect($liveManagementView)->toContain('aria-label="Release Manager"');
    expect($liveManagementView)->toContain('aria-label="Reset"');
    expect($liveManagementView)->toContain('aria-label="Add Set"');
    expect($liveManagementView)->toContain('scheduleSave() {');
    expect($liveManagementView)->toContain('this.saveTimer = setTimeout(() => this.saveState(), 500);');
    expect($liveManagementView)->toContain('if (this.saveBusy) {');
    expect($liveManagementView)->toContain('if (this.saveQueued) {');
    expect($liveManagementView)->toContain('const savedState = this.sets.map(set => this.stateSnapshot(set));');
    expect($liveManagementView)->toContain('this.originalSets = savedState;');
    expect($liveManagementView)->toContain('setTimeout(() => this.saveState(), 2000);');
    expect($liveManagementView)->toContain("x-text=\"saveError || 'Saving…'\"");
    expect($liveManagementView)->not->toContain('aria-label="Update"');
});

test('slot editing remains clickable while drag reordering ignores interactive controls', function () {
    $slotRowView = file_get_contents(resource_path('views/components/sessions/slot-assignee-pill.blade.php'));
    $songSlotsView = file_get_contents(resource_path('views/components/sessions/song-slots.blade.php'));
    $slotRowComponent = file_get_contents(resource_path('views/components/sessions/slot-row.blade.php'));
    $dragUtility = file_get_contents(resource_path('js/utils/drag.js'));
    $appJs = file_get_contents(resource_path('js/app.js'));

    expect($slotRowView)->toContain('@click.stop="openEditSlotModal()"');
    expect($songSlotsView)->toContain(':current-user-id="$currentUserId"');
    expect($slotRowComponent)->toContain(':current-user-id="$currentUserId"');
    expect($slotRowComponent)->toContain('jam_manager_id === $currentUserId');
    expect($slotRowComponent)->toContain(':can-edit-slot="$canEditSlot"');
    expect($slotRowComponent)->toContain('align-middle transition hover:bg-slate-50/70 md:align-top');
    expect($slotRowComponent)->toContain('flex items-center justify-end gap-2 md:items-start');
    expect($slotRowComponent)->toContain('@dragstart.self="onSlotDragStart($event, {{ $slotModel->id }})"');
    expect($slotRowComponent)->toContain('@dragend.self="onSlotDragEnd()"');
    expect($slotRowComponent)->toContain('inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden');
    expect($slotRowComponent)->toContain('aria-label="Move slot up"');
    expect($slotRowComponent)->toContain('aria-label="Move slot down"');
    expect($slotRowComponent)->toContain('border-t border-slate-200');
    expect($dragUtility)->toContain('export function isInteractiveDragSource(event) {');
    expect($appJs)->toContain('window.isInteractiveDragSource = isInteractiveDragSource;');
});

test('song cards use the song reorder capability for drag and ordering controls', function () {
    $songCardComponent = file_get_contents(resource_path('views/components/sessions/song-card.blade.php'));
    $setCardComponent = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));
    $sessionCards = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($songCardComponent)->toContain('data-song-drag-handle');
    expect($songCardComponent)->toContain("x-bind:draggable=\"isDesktopReorderEnabled && canReorderSongs && !(jamSessionClosed && !isAdminUser) ? 'true' : 'false'\"");
    expect($songCardComponent)->toContain('select-none flex-wrap items-center justify-between gap-3 md:items-start md:!cursor-grab md:active:!cursor-grabbing');
    expect($songCardComponent)->toContain("'canReorderSongs' => \$canReorderSongs,");
    expect($songCardComponent)->not->toContain("'canReorderSlots' => \$canManageSet");
    expect($songCardComponent)->not->toContain("\$dispatch('song-drag-start'");
    expect($setCardComponent)->toContain("@dragstart=\"onSongDragStart(\$event, Number(\$event.target.closest('[data-song-id]')?.dataset.songId))\"");
    expect($setCardComponent)->toContain("@dragover=\"onSongDragOver(\$event, Number(\$event.target.closest('[data-song-id]')?.dataset.songId) || null)\"");
    expect($setCardComponent)->toContain('@dragend="onSongDragEnd()"');
    expect($setCardComponent)->toContain('<p class="hidden text-xs text-slate-500 md:block">Tip: drag songs and slots to reorder them.</p>');
    expect($sessionCards)->toContain('canReorderSongs: config.canReorderSongs,');
    expect($sessionCards)->toContain('mobileSongReorderBusy: false,');
    expect($sessionCards)->toContain("new CustomEvent('song-reorder-start', {");
    expect($sessionCards)->toContain("new CustomEvent('song-reorder-complete', {");
    expect($sessionCards)->toContain("isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,");
    expect($sessionCards)->toContain('syncDesktopReorderEnabled() {');
    expect($setCardComponent)->toContain('@resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"');
    expect($songCardComponent)->toContain('@resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"');
    expect($songCardComponent)->toContain('x-on:song-reorder-start.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = true"');
    expect($songCardComponent)->toContain('x-on:song-reorder-complete.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = false"');
    expect($songCardComponent)->toContain('if (!mobileSongReorderBusy) { mobileSongReorderBusy = true; window.dispatchEvent(new CustomEvent(\'mobile-song-move\'');
    expect($songCardComponent)->toContain("x-bind:disabled=\"{{ \$canMoveSongUp ? 'false' : 'true' }} || mobileSongReorderBusy ||");
    expect($songCardComponent)->toContain("x-bind:disabled=\"{{ \$canMoveSongDown ? 'false' : 'true' }} || mobileSongReorderBusy ||");
    expect($songCardComponent)->toContain('inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden');
    expect($songCardComponent)->toContain('aria-label="Move song up"');
    expect($songCardComponent)->toContain('aria-label="Move song down"');
    expect($sessionCards)->toContain("this.reorderFeedback = 'Song order saved.';\n                this.refreshSessionSets();");
});

test('management set cards collapse from the full card surface', function () {
    $view = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));
    $manageView = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('@click.stop="setCollapsed = !setCollapsed"');
    expect($view)->toContain('role="button"');
    expect($view)->toContain('x-show="!setCollapsed" x-transition');
    expect($manageView)->toContain('@dragstart.self="onSetDragStart($event, set)"');
    expect($manageView)->toContain('@click="toggleSetSongs(set)"');
    expect($manageView)->toContain('x-show="set.songs.length > 0"');
    expect($manageView)->toContain('x-bind:aria-expanded="(!set.songsCollapsed).toString()"');
    expect($manageView)->toContain('x-show="set.songs.length > 0 && !set.songsCollapsed" x-transition.opacity.duration.150ms');
    expect($manageView)->toContain('collapsedSetSongIds: new Set(),');
    expect($manageView)->toContain('return `live-jam-collapsed-set-songs:${config.dataUrl}`;');
    expect($manageView)->toContain('toggleSetSongs(set) {');
});

test('session set, song, and slot details animate when toggled', function () {
    $setCard = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));
    $songCard = file_get_contents(resource_path('views/components/sessions/song-card.blade.php'));

    expect($setCard)->toContain('x-show="!setCollapsed" x-transition.opacity.duration.150ms');
    expect($songCard)->toContain('x-show="!songCollapsed" x-transition');
    expect($songCard)->toContain('x-show="!songCollapsed" x-transition>');
});

test('set and song action menus use viewport-aware positioning', function () {
    $setCard = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));
    $songCard = file_get_contents(resource_path('views/components/sessions/song-card.blade.php'));
    $cardScripts = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($setCard)->toContain('x-ref="actionMenuButton"');
    expect($setCard)->toContain('x-teleport="body"');
    expect($setCard)->toContain('x-bind:style="actionMenuStyle"');
    expect($setCard)->toContain('@scroll.window="repositionActionMenu()"');
    expect($songCard)->toContain('x-ref="actionMenuButton"');
    expect($songCard)->toContain('x-teleport="body"');
    expect($songCard)->toContain('x-bind:style="actionMenuStyle"');
    expect($songCard)->toContain('@resize.window="repositionActionMenu()"');
    expect($cardScripts)->toContain('function viewportActionMenuStyle(button)');
    expect($cardScripts)->toContain('this.actionMenuStyle = viewportActionMenuStyle(this.$refs.actionMenuButton);');
});

test('edit set modal keeps its header and original actions outside the scrollable form body', function () {
    $setCard = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));

    expect($setCard)->toContain('flex-col overflow-hidden rounded-xl');
    expect($setCard)->toContain('<div class="px-6 pt-6">');
    expect($setCard)->toContain('<div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">');
    expect($setCard)->toContain('class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4"');
    expect($setCard)->toContain('Delete Set');
    expect($setCard)->toContain('form="edit_set_form_{{ $set->id }}"');
});

test('add song modal keeps its actions outside the scrollable form body', function () {
    $setCard = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));

    expect($setCard)->toContain('class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitAddSong($event)"');
    expect($setCard)->toContain('class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4"');
    expect($setCard)->toContain('class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4"');
});

test('live management normalizes stack order and queues saves after each mutation', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('normalizeSetOrders() {');
    expect($view)->toContain("['playing_now', 'coming_up', 'pending', 'postponed', 'finished'].forEach(status => {");
    expect($view)->toContain('this.normalizeSetOrders();');
    expect($view)->toContain('this.applyOrderedIdsForStatus(draggedSet.status, orderedIds);');
    expect($view)->toContain('this.scheduleSave();');
    expect($view)->toContain("replaceSetWithAnimation(setId, changes) {\n                const previousRects");
    expect($view)->toContain("this.animateSetMovement(previousRects);\n                this.scheduleSave();\n            },\n\n            canDragSet");
    expect($view)->not->toContain('p-4 text-slate-100 shadow-sm">\n                this.scheduleSave();');
    expect($view)->toContain('async saveState() {');
});
