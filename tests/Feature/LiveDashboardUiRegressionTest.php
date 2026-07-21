<?php

test('management dashboard preserves local changes when polling', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('const localIds = new Set(this.sets.map(set => String(set.id)));');
    expect($view)->toContain('this.sets = [...this.sets, ...newSets];');
});

test('management dashboard highlights new sets until browsed and keeps the updated status panel', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('@mouseenter="markSetBrowsed(set)"');
    expect($view)->toContain('x-transition:enter-start="opacity-0 translate-x-4"');
    expect($view)->toContain('text-emerald-400');
    expect($view)->toContain('text-amber-400');
    expect($view)->toContain('text-slate-500');
    expect($view)->toContain('x-text="jamManagerName || \'No jam manager assigned yet\'"');
    expect($view)->toContain('h-6 w-6');
    expect($view)->toContain('lg:text-right');
    expect($view)->toContain('Last saved <span x-text="lastUpdated"></span>');
    expect($view)->toContain('<x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />');
    expect($view)->toContain('Arrange sets, then update the live display.');
    expect($view)->not->toContain('Run of show');
});

test('live dashboard uses the updated slot badge treatment', function () {
    $view = file_get_contents(resource_path('views/sessions/live/dashboard.blade.php'));

    expect($view)->toContain('slotBadgeClasses(slot)');
    expect($view)->toContain('border-emerald-800 bg-emerald-950/60 text-emerald-300 ring-1 ring-emerald-800');
    expect($view)->toContain('border-amber-800 bg-amber-950/60 text-amber-300 ring-1 ring-amber-800');
    expect($view)->toContain('border-slate-700 bg-slate-800 text-slate-400');
});

test('slot editing remains clickable while drag reordering ignores interactive controls', function () {
    $slotRowView = file_get_contents(resource_path('views/components/sessions/slot-assignee-pill.blade.php'));
    $songSlotsView = file_get_contents(resource_path('views/components/sessions/song-slots.blade.php'));
    $slotRowComponent = file_get_contents(resource_path('views/components/sessions/slot-row.blade.php'));
    $sessionCardsJs = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($slotRowView)->toContain('@click.stop="openEditSlotModal()"');
    expect($songSlotsView)->toContain(':current-user-id="$currentUserId"');
    expect($slotRowComponent)->toContain(':current-user-id="$currentUserId"');
    expect($slotRowComponent)->toContain('jam_manager_id === $currentUserId');
    expect($slotRowComponent)->toContain(':can-edit-slot="$canEditSlot"');
    expect($sessionCardsJs)->toContain("closest('button, a, input, select, textarea, label')");
});

test('management set cards collapse from the full card surface', function () {
    $view = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));

    expect($view)->toContain("@click=\"if (!\$event.target.closest('button, a, input, select, textarea, label')) { setCollapsed = !setCollapsed; }\"");
    expect($view)->toContain('role="button"');
    expect($view)->toContain('x-show="!setCollapsed" x-transition');
});
