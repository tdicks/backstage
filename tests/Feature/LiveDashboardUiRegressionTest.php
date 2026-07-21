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
    expect($view)->toContain('entry.intersectionRatio < 0.6');
    expect($view)->toContain('this.fadeSetHighlight(set);');
    expect($view)->toContain('}, 1200));');
    expect($view)->toContain('set.highlightFading = true;');
    expect($view)->toContain('set.highlightFading = false;');
    expect($view)->toContain('{ threshold: 0.6 },');
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

    expect($view)->toContain('slotBadgeClasses(slot)');
    expect($view)->toContain('border-emerald-300 bg-emerald-900/80 text-emerald-50');
    expect($view)->toContain('bg-emerald-950/60 text-emerald-300');
    expect($view)->toContain('bg-slate-800 text-slate-500');
    expect($view)->not->toContain('ring-1 ring-emerald-400/80');
    expect($view)->not->toContain('ring-1 ring-emerald-800');
});

test('live management assignment badges open and save through the assignment editor', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));
    $slotEditModal = file_get_contents(resource_path('views/components/sessions/slot-edit-modal.blade.php'));
    $sessionCards = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($view)->toContain('@click="openEditSlotModal(set, song, slot)"');
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
    expect($slotRowComponent)->toContain('@dragstart.self="onSlotDragStart($event, {{ $slotModel->id }})"');
    expect($slotRowComponent)->toContain('@dragend.self="onSlotDragEnd()"');
    expect($dragUtility)->toContain('export function isInteractiveDragSource(event) {');
    expect($appJs)->toContain('window.isInteractiveDragSource = isInteractiveDragSource;');
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

test('live management normalizes stack order after each mutation and before saving', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('normalizeSetOrders() {');
    expect($view)->toContain("['playing_now', 'coming_up', 'pending', 'postponed', 'finished'].forEach(status => {");
    expect($view)->toContain('this.normalizeSetOrders();');
    expect($view)->toContain('this.applyOrderedIdsForStatus(draggedSet.status, orderedIds);');
    expect($view)->toContain('async saveState() {');
});
