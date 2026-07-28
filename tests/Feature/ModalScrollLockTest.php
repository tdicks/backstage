<?php

test('modal overlays lock background scrolling while modal bodies remain scrollable', function () {
    $app = file_get_contents(resource_path('js/app.js'));
    $css = file_get_contents(resource_path('css/app.css'));
    $sessionView = file_get_contents(resource_path('views/sessions/show.blade.php'));
    $setCard = file_get_contents(resource_path('views/components/sessions/set-card.blade.php'));

    expect($app)->toContain("document.querySelectorAll('[data-modal-overlay]')")
        ->toContain("document.documentElement.classList.toggle('modal-scroll-locked', hasOpenModal);")
        ->toContain("document.body.classList.toggle('modal-scroll-locked', hasOpenModal);")
        ->toContain('new MutationObserver(syncModalScrollLock).observe(document.body, {');

    expect($css)->toContain('html.modal-scroll-locked,')
        ->toContain('body.modal-scroll-locked {')
        ->toContain('overflow: hidden;');

    expect($sessionView)->toContain('data-drag-blocking-modal data-modal-overlay')
        ->toContain('fixed inset-0 z-50 flex items-start justify-center overflow-y-auto')
        ->toContain('min-h-0 flex-1 overflow-y-auto px-6 py-4');

    expect($setCard)->toContain('data-drag-blocking-modal data-modal-overlay')
        ->toContain('min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6');
});
