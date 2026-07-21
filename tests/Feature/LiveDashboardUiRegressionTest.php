<?php

test('live management dashboard preserves local changes and highlights new sets until browsed', function () {
    $view = file_get_contents(resource_path('views/sessions/live/manage.blade.php'));

    expect($view)->toContain('const localIds = new Set(this.sets.map(set => String(set.id)));');
    expect($view)->toContain('this.sets = [...this.sets, ...newSets];');
    expect($view)->toContain('@mouseenter="markSetBrowsed(set)"');
    expect($view)->toContain('x-transition:enter-start="opacity-0 translate-x-4"');
    expect($view)->toContain('Last saved <span x-text="lastUpdated"></span>');
    expect($view)->toContain('x-show="canManageLiveJam"');
    expect($view)->not->toContain('Run of show');
});

test('live dashboard uses the updated slot badge treatment', function () {
    $view = file_get_contents(resource_path('views/sessions/live/dashboard.blade.php'));

    expect($view)->toContain('slotBadgeClasses(slot)');
    expect($view)->toContain('border-emerald-200 bg-emerald-50/90 text-emerald-800');
    expect($view)->toContain('border-amber-200 bg-amber-50/80 text-amber-800');
    expect($view)->toContain('border-slate-200 bg-slate-50 text-slate-500');
});

test('slot editing remains clickable while drag reordering ignores interactive controls', function () {
    $slotRowView = file_get_contents(resource_path('views/components/sessions/slot-assignee-pill.blade.php'));
    $sessionCardsJs = file_get_contents(resource_path('js/components/sessionCards.js'));

    expect($slotRowView)->toContain('@click.stop="openEditSlotModal()"');
    expect($sessionCardsJs)->toContain("closest('button, a, input, select, textarea, label')");
});
