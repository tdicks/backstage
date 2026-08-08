<?php

test('live management assignment badges open and save through the assignment editor', function () {
    $view = regressionResource('views/sessions/live/manage.blade.php');
    $slotEditModal = regressionResource('views/components/sessions/slot-edit-modal.blade.php');
    $sessionCards = regressionJs('components/sessionCards.js');

    expectContainsAll($view, [
        '@click.stop="toggleLiveSlotActions(set, song, slot)"',
        '@click="openLiveSlotEditor(set, song, slot)"',
        '@click="clearLiveSlotAssignment(set, song, slot)"',
        'x-show="slot.filled"',
        'Slot actions',
        'Edit Slot',
        'Clear Slot',
        '<template x-if="slot.manual_performer_name">',
        '<x-heroicon-m-pencil-square class="h-3 w-3" aria-hidden="true" />',
        'title="Manually assigned"',
        '<x-sessions.slot-edit-modal :slot-options="$slotOptions" :users="$assignmentUsers" live-dashboard />',
        'async submitLiveSlotEdit()',
        "config.slotUpdateUrlTemplate.replace('__slot__', slot.id)",
        'assignmentConflictMessage: \'\',',
        'showAssignmentConflict(message) {',
        'Click Save to move the assignment.',
        'replace_conflicting_assignment: this.assignmentConflictPending,',
        'async clearLiveSlotAssignment(set, song, slot) {',
        'replace_conflicting_assignment: false,',
    ]);

    expectContainsAll($slotEditModal, [
        "'liveDashboard' => false",
        'x-show="openEditSlot"',
        'Assigned User or Manual Name',
        'x-show="assignmentConflictMessage"',
        'assignmentSaveBusy || assignmentConflictCooldown',
        'class="disabled:cursor-not-allowed disabled:opacity-40"',
    ]);

    expectNotContainsAny($slotEditModal, ['@click="deleteLiveSlot()"', 'Move assignment', 'Reviewing...']);
    expectNotContainsAny($view, ['window.confirm(`${conflict.message} Continue?`)']);

    expectContainsAll($sessionCards, [
        'showAssignmentConflict(message) {',
        'Click Save to move the assignment.',
        "formData.set('replace_conflicting_assignment', '1');",
    ]);

    expectNotContainsAny($sessionCards, ['window.confirm(`${conflict.message} Continue?`)']);
});

test('slot editing remains clickable while drag reordering ignores interactive controls', function () {
    $slotRowView = regressionResource('views/components/sessions/slot-assignee-pill.blade.php');
    $songSlotsView = regressionResource('views/components/sessions/song-slots.blade.php');
    $slotRowComponent = regressionResource('views/components/sessions/slot-row.blade.php');
    $dragUtility = file_get_contents(base_path('resources/js/utils/drag.js'));
    $appJs = regressionJs('app.js');

    expectContainsAll($slotRowView, ['@click.stop="openEditSlotModal()"']);
    expectContainsAll($songSlotsView, [':current-user-id="$currentUserId"']);

    expectContainsAll($slotRowComponent, [
        'jam_manager_id === $currentUserId',
        ':can-edit-slot="$canEditSlot"',
        'align-middle transition hover:bg-slate-50/70 md:align-top',
        'flex items-center justify-end gap-2 md:items-start',
        '@dragstart.stop.self="onSlotDragStart($event, {{ $slotModel->id }})"',
        '@dragover.stop="onSlotDragOver($event, Number($event.target.closest(\'[data-slot-id]\')?.dataset.slotId) || null)"',
        '@dragend.stop.self="onSlotDragEnd()"',
        'inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden',
        'aria-label="Move slot up"',
        'aria-label="Move slot down"',
        'border-t border-slate-200',
        "'canMoveSlotUp' => \$canMoveSlotUp,",
        "'canMoveSlotDown' => \$canMoveSlotDown,",
        'x-on:slot-order-changed.window="if ($event.detail.songId === {{ $slotModel->song_id }}) syncMobileSlotOrder()"',
        'x-bind:disabled="!canMoveSlotUp || busyAction ||',
        'x-bind:disabled="!canMoveSlotDown || busyAction ||',
    ]);

    expectContainsAll($dragUtility, ['export function isInteractiveDragSource(event) {']);
    expectContainsAll($appJs, ['window.isInteractiveDragSource = isInteractiveDragSource;']);
});

test('song cards use the song reorder capability for drag and ordering controls', function () {
    $songCardComponent = regressionResource('views/components/sessions/song-card.blade.php');
    $setCardComponent = regressionResource('views/components/sessions/set-card.blade.php');
    $sessionCards = regressionJs('components/sessionCards.js');

    expectContainsAll($songCardComponent, [
        'data-song-drag-handle',
        'x-bind:draggable="isDesktopReorderEnabled && canReorderSongs && !(jamSessionClosed && !isAdminUser) ? \'true\' : \'false\'"',
        'select-none flex-wrap items-center justify-between gap-3 md:items-start',
        "'canReorderSongs' => \$canReorderSongs,",
        "'songId' => \$song->id,",
        "'canMoveSongUp' => \$canMoveSongUp,",
        "'canMoveSongDown' => \$canMoveSongDown,",
        "'canReorderSlots' => \$canManageSet && ! \$setLocked && ! (\$jamSessionClosed && ! auth()->user()?->is_admin),",
        '@resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"',
        'x-on:song-reorder-start.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = true"',
        'x-on:song-reorder-complete.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = false"',
        'x-on:song-order-changed.window="if ($event.detail.setId === {{ $set->id }}) syncMobileSongOrder()"',
        "if (!mobileSongReorderBusy) { mobileSongReorderBusy = true; window.dispatchEvent(new CustomEvent('mobile-song-move'",
        'x-bind:disabled="!canMoveSongUp || mobileSongReorderBusy ||',
        'x-bind:disabled="!canMoveSongDown || mobileSongReorderBusy ||',
        'inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden',
        'aria-label="Move song up"',
        'aria-label="Move song down"',
    ]);

    expectNotContainsAny($songCardComponent, ['cursor-grab', '\$dispatch(\'song-drag-start\'']);

    expectContainsAll($setCardComponent, [
        '@dragstart="onSongDragStart($event, Number($event.target.closest(\'[data-song-id]\')?.dataset.songId))"',
        '@dragover="onSongDragOver($event, Number($event.target.closest(\'[data-song-id]\')?.dataset.songId) || null)"',
        '@dragend="onSongDragEnd()"',
        '<p class="hidden text-xs text-slate-500 md:block">Tip: drag songs and slots to reorder them.</p>',
        '@resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"',
    ]);

    expectContainsAll($sessionCards, [
        'canReorderSongs: config.canReorderSongs,',
        'mobileSongReorderBusy: false,',
        'songId: config.songId,',
        'canMoveSongUp: config.canMoveSongUp,',
        'canMoveSongDown: config.canMoveSongDown,',
        "new CustomEvent('slot-order-changed', {",
        'syncMobileSlotOrder() {',
        "new CustomEvent('song-order-changed', {",
        'syncMobileSongOrder() {',
        "new CustomEvent('song-reorder-start', {",
        "new CustomEvent('song-reorder-complete', {",
        "isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,",
        'syncDesktopReorderEnabled() {',
        "this.reorderFeedback = 'Song order saved.';\n                window.dispatchEvent(new CustomEvent('song-reorder-complete', {",
        "this.showActionFeedback('Slot order saved.');",
    ]);

    expectNotContainsAny($sessionCards, [
        "this.reorderFeedback = 'Song order saved.';\n                this.refreshSessionSets();",
        "this.actionFeedback = 'Slot order saved.';\n                this.refreshSessionSets();",
    ]);
});

test('management set cards collapse from the full card surface', function () {
    $view = regressionResource('views/components/sessions/set-card.blade.php');
    $songCard = regressionResource('views/components/sessions/song-card.blade.php');
    $manageView = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($view, [
        '@click.stop="setCollapsed = !setCollapsed"',
        'role="button"',
        'x-show="!setCollapsed" x-transition',
        '<x-heroicon-m-chevron-up x-show="!setCollapsed"',
        '<x-heroicon-m-chevron-down x-show="setCollapsed"',
        '<span class="inline-flex shrink-0 items-center">',
    ]);

    expectContainsAll($songCard, [
        '<x-heroicon-m-chevron-up x-show="!songCollapsed"',
        '<x-heroicon-m-chevron-down x-show="songCollapsed"',
    ]);

    expectContainsAll($manageView, [
        '@dragstart.self="onSetDragStart($event, set)"',
        '@click="toggleSetSongs(set)"',
        'x-show="set.songs.length > 0"',
        'x-bind:aria-expanded="(!set.songsCollapsed).toString()"',
        '<div class="flex flex-nowrap items-start justify-between gap-3">',
        '<div class="min-w-0 flex-1">',
        'class="block text-left text-xl font-semibold text-slate-100 transition hover:text-white focus:outline-none"',
        '<span class="inline-flex items-center align-middle whitespace-nowrap">&nbsp;<x-heroicon-m-chevron-down',
        '<div class="flex shrink-0 items-center gap-2">',
        'x-show="set.songs.length > 0 && !set.songsCollapsed" x-transition.opacity.duration.150ms',
        'collapsedSetSongIds: new Set(),',
        'return `live-jam-collapsed-set-songs:${config.dataUrl}`;',
        'toggleSetSongs(set) {',
        "if (set.feature_set) {\n                    stateClasses.push('!border-amber-400');",
    ]);
});
