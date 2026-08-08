<?php

test('management dashboard preserves local changes when polling', function () {
    $view = regressionResource('views/sessions/live/manage.blade.php');
    $dashboardView = regressionResource('views/sessions/live/dashboard.blade.php');

    expectContainsAll($view, [
        'init() {',
        'const localSetsById = new Map(this.sets.map(set => [String(set.id), set]));',
        'const refreshedSets = serverSets.map((serverSet) => {',
        '...serverSet,',
        'const localOnlySets = this.sets.filter(set => !serverSetIds.has(String(set.id)));',
        'this.sets = [...refreshedSets, ...localOnlySets];',
        'async fetchData({ force = false } = {}) {',
        'if (!force && (this.saveQueued || this.saveBusy || this.hasChanges)) {',
        'await this.fetchData({ force: true });',
    ]);

    expectNotContainsAny($view, ['x-init="init()"']);
    expectContainsAll($dashboardView, ['init() {']);
    expectNotContainsAny($dashboardView, ['x-init="init()"']);
});

test('management dashboard highlights new sets until browsed and keeps the updated status panel', function () {
    $view = regressionResource('views/sessions/live/manage.blade.php');
    $css = regressionCss('app.css');

    expectContainsAll($view, [
        'x-init="observeSetCard($el, set)"',
        ':class="setCardClasses(set)"',
        'seenSetIds: new Set(),',
        'seenObserver: null,',
        'seenDwellTimers: new Map(),',
        'return `live-jam-seen-sets:${config.dataUrl}`;',
        "this.seenSetIds = new Set(JSON.parse(localStorage.getItem(this.seenSetIdsKey()) || '[]'));",
        'set.highlighted = !this.seenSetIds.has(String(set.id));',
        'this.markSetSeen(set);',
        'handleSetVisibility(entries) {',
        'entry.intersectionRatio < 0.2',
        'this.fadeSetHighlight(set);',
        '}, 1200));',
        'set.highlightFading = true;',
        'set.highlightFading = false;',
        '{ threshold: 0.2 },',
        "stateClasses.push('live-set-unseen');",
        "stateClasses.push('live-set-unseen-exit');",
        'this.sets = serverSets.map(serverSet => this.applyHighlightIfNeeded({ ...serverSet }));',
        'x-transition:enter-start="opacity-0 translate-x-4"',
        'text-emerald-400',
        'text-amber-400',
        'text-slate-500',
        'h-6 w-6',
        '<x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />',
    ]);

    expectNotContainsAny($view, [
        'serverSets.forEach(set => this.seenSetIds.add(String(set.id)));',
        'this.markSetSeen(newSet);',
        'lastCheckedAt: null,',
        'Run of show',
        'Last saved <span x-text="lastUpdated"></span>',
    ]);

    expectContainsAll($css, [
        '.live-set-unseen {',
        'animation: live-set-unseen-pulse 1.6s ease-in-out infinite;',
        'border-color: rgb(56 189 248 / 0.8) !important;',
        'box-shadow: 0 0 24px rgb(56 189 248 / 0.44);',
        '.live-set-unseen-exit {',
        'animation: live-set-unseen-exit 450ms ease-out forwards;',
    ]);
});

test('live management normalizes stack order and queues saves after each mutation', function () {
    $view = regressionResource('views/sessions/live/manage.blade.php');

    expectContainsAll($view, [
        'normalizeSetOrders() {',
        "['playing_now', 'coming_up', 'pending', 'postponed', 'finished'].forEach(status => {",
        'this.normalizeSetOrders();',
        'this.applyOrderedIdsForStatus(draggedSet.status, orderedIds);',
        'this.scheduleSave();',
        "replaceSetWithAnimation(setId, changes) {\n                const previousRects",
        "this.animateSetMovement(previousRects);\n                this.scheduleSave();\n            },\n\n            canDragSet",
        'async saveState() {',
    ]);

    expectNotContainsAny($view, ['p-4 text-slate-100 shadow-sm">\n                this.scheduleSave();']);
});
