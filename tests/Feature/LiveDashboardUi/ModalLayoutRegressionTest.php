<?php

test('live add set modal keeps its header and actions fixed', function () {
    $manageView = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($manageView, [
        'flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden',
        'min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4',
        'flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4',
    ]);
});

test('session set, song, and slot details animate when toggled', function () {
    $setCard = regressionResource('views/components/sessions/set-card.blade.php');
    $songCard = regressionResource('views/components/sessions/song-card.blade.php');

    expectContainsAll($setCard, ['x-show="!setCollapsed" x-transition.opacity.duration.150ms']);
    expectContainsAll($songCard, ['x-show="!songCollapsed" x-transition', 'x-show="!songCollapsed" x-transition>']);
});

test('set and song action menus use viewport-aware positioning', function () {
    $setCard = regressionResource('views/components/sessions/set-card.blade.php');
    $songCard = regressionResource('views/components/sessions/song-card.blade.php');
    $cardScripts = regressionJs('components/sessionCards.js');

    expectContainsAll($setCard, [
        'x-ref="actionMenuButton"',
        'x-teleport="body"',
        'x-bind:style="actionMenuStyle"',
        '@scroll.window="repositionActionMenu()"',
    ]);

    expectContainsAll($songCard, [
        'x-ref="actionMenuButton"',
        'x-teleport="body"',
        'x-bind:style="actionMenuStyle"',
        '@resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"',
    ]);

    expectContainsAll($cardScripts, [
        'function viewportActionMenuStyle(button, menu = null)',
        'this.actionMenuStyle = viewportActionMenuStyle(this.$refs.actionMenuButton, this.$refs.actionMenu);',
    ]);
});

test('edit set modal keeps its header and original actions outside the scrollable form body', function () {
    $setCard = regressionResource('views/components/sessions/set-card.blade.php');

    expectContainsAll($setCard, [
        'flex-col overflow-hidden rounded-xl',
        '<div class="px-6 pt-6">',
        '<div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">',
        'class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4"',
        "onsubmit=\"return confirm('Move this set to the Recycle Bin?');\"",
        'Move Set to Recycle Bin',
        'form="edit_set_form_{{ $set->id }}"',
    ]);
});

test('add song modal keeps its actions outside the scrollable form body', function () {
    $setCard = regressionResource('views/components/sessions/set-card.blade.php');
    $sessionCards = regressionJs('components/sessionCards.js');

    expectContainsAll($setCard, [
        'class="flex min-h-0 flex-1 flex-col" x-data="{ songSlotAdditionMode: \'template\' }" @submit.prevent="submitAddSong($event)"',
        'class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4"',
        'class="flex shrink-0 justify-end gap-3 border-t border-slate-200 px-6 py-4"',
        "x-data=\"{ songSlotAdditionMode: 'template' }\"",
        'Add slots by',
        'Choose slots manually',
        "x-show=\"songSlotAdditionMode === 'template'\"",
        "x-show=\"songSlotAdditionMode === 'manual'\"",
    ]);

    expectContainsAll($sessionCards, [
        "const hasBandTemplate = Boolean(formData.get('band_template_id'));",
        "const hasManualSlots = formData.getAll('slot_names[]').length > 0;",
        'No slots will be added to this song now. You can add slots later. Continue?',
    ]);
});
