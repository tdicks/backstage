<?php

use App\Models\User;
use App\Support\FeatureTourConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('feature tour config validator passes with current config', function () {
    $exitCode = Artisan::call('app:validate-feature-tours-config');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Feature tour config is valid.');
});

test('feature tour config validator reports unknown anchor references', function () {
    $path = resource_path('tours/10-welcome.yaml');
    $original = File::get($path);

    File::put($path, <<<'YAML'
version: 1

anchors:
    known-anchor: '#known-anchor'

actions:
    open-known:
        type: click
        target: known-anchor
    open-missing:
        type: click
        target: missing-action-anchor

tours:
    broken-tour:
        once_key: broken-tour
        trigger:
            mode: button
            button:
                target: missing-button-anchor
        routes:
            - dashboard
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'Broken'
                      body: 'This step is intentionally invalid.'
                      target: missing-step-anchor
                      before:
                        - open-known
                        - open-missing
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Feature tour config validation failed.')
            ->and($output)->toContain('button.target references unknown anchor [missing-button-anchor].')
            ->and($output)->toContain('references unknown anchor [missing-step-anchor].');
    } finally {
        File::put($path, $original);
    }
});

test('feature tour config validator warns about unreferenced anchors and actions', function () {
    $path = resource_path('tours/10-welcome.yaml');
    $original = File::get($path);

    File::put($path, <<<'YAML'
version: 1

anchors:
    used-anchor: '#used-anchor'
    unused-anchor: '#unused-anchor'

actions:
    use-anchor:
        type: click
        target: used-anchor
    unused-action:
        type: click
        target: used-anchor

tours:
    warning-tour:
        once_key: warning-tour
        trigger:
            mode: button
            button:
                target: used-anchor
        routes:
            - dashboard
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'Warning'
                      body: 'This tour is valid but leaves one anchor and one action unused.'
                      target: used-anchor
                      before:
                        - use-anchor
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('WARNING: Anchor [unused-anchor] is defined but never referenced by any tour target or action.')
            ->and($output)->toContain('WARNING: Action [unused-action] is defined but never referenced by any tour step or trigger.');
    } finally {
        File::put($path, $original);
    }
});

test('feature tour config validator accepts info-icon trigger mode without a button target', function () {
    $path = resource_path('tours/001-info-icon-trigger-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    info-icon-tour:
        once_key: info-icon-tour-v1
        authenticated: true
        priority: 10
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Info icon'
                      body: 'This tour starts from the info icon.'
                      target: '#info-icon-trigger-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour config validator accepts info-icon-always trigger mode without a button target', function () {
    $path = resource_path('tours/001-info-icon-always-trigger-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    info-icon-always-tour:
        once_key: info-icon-always-tour-v1
        authenticated: true
        priority: 10
        trigger:
            mode: info-icon-always
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Always icon'
                      body: 'This tour always keeps the info icon available.'
                      target: '#info-icon-always-trigger-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour config validator accepts prompt trigger with show_info_icon enabled', function () {
    $path = resource_path('tours/001-prompt-show-info-icon-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    prompt-info-icon-tour:
        once_key: prompt-info-icon-tour-v1
        authenticated: true
        trigger:
            mode: prompt
            show_info_icon: true
            prompt:
                question: 'Want a quick tour?'
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Prompt tour'
                      body: 'Info icon should be available during this prompt tour.'
                      target: '#prompt-info-icon-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves trigger.show_info_icon', function () {
    $path = resource_path('tours/001-prompt-show-info-icon-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    prompt-info-icon-payload-tour:
        once_key: prompt-info-icon-payload-tour-v1
        authenticated: true
        trigger:
            mode: prompt
            show_info_icon: true
            prompt:
                question: 'Want a quick tour?'
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Prompt payload tour'
                      body: 'Payload should preserve show_info_icon.'
                      target: '#prompt-info-icon-payload-target'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

        expect($payload['tours']['prompt-info-icon-payload-tour']['trigger']['show_info_icon'] ?? null)
            ->toBeTrue();
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts admin_only as a boolean flag', function () {
    $path = resource_path('tours/001-admin-only-tour-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    admin-only-tour:
        once_key: admin-only-tour-v1
        authenticated: true
        admin_only: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Admin only'
                      body: 'This tour is only for admin users.'
                      target: '#admin-only-tour-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects non-boolean admin_only values', function () {
    $path = resource_path('tours/001-admin-only-invalid-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    invalid-admin-only-tour:
        once_key: invalid-admin-only-tour-v1
        authenticated: true
        admin_only: 'yes'
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid admin only'
                      body: 'This value should be rejected.'
                      target: '#invalid-admin-only-tour-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('admin_only must be true or false.');
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves admin_only and user role flag', function () {
    $path = resource_path('tours/001-admin-only-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    admin-only-payload-tour:
        once_key: admin-only-payload-tour-v1
        authenticated: true
        admin_only: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Admin payload'
                      body: 'Payload should preserve admin-only config.'
                      target: '#admin-only-payload-tour-target'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make(['is_admin' => true]));

        expect($payload['is_admin'] ?? null)->toBeTrue()
            ->and($payload['tours']['admin-only-payload-tour']['admin_only'] ?? null)->toBeTrue();
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts step-level admin_only as a boolean flag', function () {
    $path = resource_path('tours/001-step-admin-only-tour-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    step-admin-only-tour:
        once_key: step-admin-only-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'General step'
                      body: 'Visible to everyone.'
                      target: '#step-admin-only-general-target'
                    - title: 'Admin step'
                      body: 'Only visible to admins.'
                      admin_only: true
                      target: '#step-admin-only-admin-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects non-boolean step-level admin_only values', function () {
    $path = resource_path('tours/001-step-admin-only-invalid-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    step-admin-only-invalid-tour:
        once_key: step-admin-only-invalid-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid admin step'
                      body: 'This should fail validation.'
                      admin_only: maybe
                      target: '#step-admin-only-invalid-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('step [0] admin_only must be true or false.');
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves step-level admin_only flag', function () {
    $path = resource_path('tours/001-step-admin-only-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    step-admin-only-payload-tour:
        once_key: step-admin-only-payload-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'General step'
                      body: 'Visible to everyone.'
                      target: '#step-admin-only-payload-general-target'
                    - title: 'Admin step'
                      body: 'Only visible to admins.'
                      admin_only: true
                      target: '#step-admin-only-payload-admin-target'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make(['is_admin' => false]));
        $steps = $payload['tours']['step-admin-only-payload-tour']['variants']['default']['steps'] ?? [];

        expect($steps[0]['admin_only'] ?? null)->toBeFalse()
            ->and($steps[1]['admin_only'] ?? null)->toBeTrue();
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts trigger.modal.id', function () {
    $path = resource_path('tours/001-modal-trigger-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    modal-tour:
        once_key: modal-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
            modal:
                id: markdown-help
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Modal tour'
                      body: 'This tour is scoped to a modal.'
                      target: '#modal-tour-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves trigger.modal.id', function () {
    $path = resource_path('tours/001-modal-trigger-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    modal-tour-payload:
        once_key: modal-tour-payload-v1
        authenticated: true
        trigger:
            mode: info-icon
            modal:
                id: modal-shell
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Modal payload'
                      body: 'Payload should preserve modal id.'
                      target: '#modal-tour-payload-target'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

        expect($payload['tours']['modal-tour-payload']['trigger']['modal']['id'] ?? null)
            ->toBe('modal-shell');
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects empty trigger.modal.id when trigger.modal is provided', function () {
    $path = resource_path('tours/001-invalid-modal-trigger-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    invalid-modal-tour:
        once_key: invalid-modal-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
            modal:
                id: ''
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid modal'
                      body: 'Empty modal id should fail validation.'
                      target: '#invalid-modal-tour-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('trigger.modal.id must be a non-empty string');
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects non-boolean trigger.show_info_icon values', function () {
    $path = resource_path('tours/001-invalid-show-info-icon-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    invalid-show-info-icon-tour:
        once_key: invalid-show-info-icon-tour-v1
        authenticated: true
        trigger:
            mode: prompt
            show_info_icon: maybe
            prompt:
                question: 'Want a quick tour?'
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid show_info_icon'
                      body: 'Non-boolean show_info_icon should fail validation.'
                      target: '#invalid-show-info-icon-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('trigger.show_info_icon must be true or false');
    } finally {
        File::delete($path);
    }
});

test('feature tour config validator accepts set-checked actions', function () {
    $path = resource_path('tours/001-set-checked-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    select-song:
        type: set-checked
        target: '#song-checkbox'
        checked: true

tours:
    set-checked-tour:
        once_key: set-checked-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Select a song'
                      body: 'This step uses a deterministic checkbox action.'
                      target: '#song-checkbox'
                      before:
                        - select-song
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves set-checked action state', function () {
    $path = resource_path('tours/001-set-checked-action-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    select-song:
        type: set-checked
        target: '#song-checkbox'
        checked: false

tours:
    set-checked-payload-tour:
        once_key: set-checked-payload-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Checkbox payload'
                      body: 'Payload should preserve the checked state.'
                      target: '#song-checkbox'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

        expect($payload['actions']['select-song']['type'] ?? null)->toBe('set-checked')
            ->and($payload['actions']['select-song']['checked'] ?? null)->toBeFalse();
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects non-boolean checked values for set-checked actions', function () {
    $path = resource_path('tours/001-invalid-set-checked-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    select-song:
        type: set-checked
        target: '#song-checkbox'
        checked: maybe

tours:
    invalid-set-checked-tour:
        once_key: invalid-set-checked-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid checkbox action'
                      body: 'This should fail validation.'
                      target: '#song-checkbox'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('field [checked] must be true or false for type [set-checked]');
    } finally {
        File::delete($path);
    }
});

test('feature tour config validator reports missing data-tour markers for step targets', function () {
    $path = resource_path('tours/001-missing-data-tour-marker-test.yaml');

    File::put($path, <<<'YAML'
version: 1

anchors:
    missing-dom-anchor: '[data-tour="totally-missing-tour-marker"]'

tours:
    missing-dom-marker-tour:
        once_key: missing-dom-marker-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Missing marker'
                      body: 'This selector should fail static view marker validation.'
                      target: missing-dom-anchor
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('missing data-tour marker [totally-missing-tour-marker]');
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves info-icon trigger mode', function () {
    $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

    expect($payload['tours']['find-a-slot']['trigger']['mode'] ?? null)->toBe('info-icon-always');
});

test('feature tour payload preserves info-icon-always trigger mode', function () {
    $path = resource_path('tours/001-info-icon-always-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    info-icon-always-payload-tour:
        once_key: info-icon-always-payload-tour-v1
        authenticated: true
        priority: 20
        trigger:
            mode: info-icon-always
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Always icon payload'
                      body: 'Payload should preserve info-icon-always mode.'
                      target: '#info-icon-always-payload-target'
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

        expect($payload['tours']['info-icon-always-payload-tour']['trigger']['mode'] ?? null)
            ->toBe('info-icon-always');
    } finally {
        File::delete($path);
    }
});

test('feature tour payload supports anchor view modes', function () {
    $path = resource_path('tours/001-anchor-view-test.yaml');

    File::put($path, <<<'YAML'
version: 1

anchors:
    test-anchor-string: '[data-tour="jam-standards-search"]'
    test-anchor-multiple:
        selector: '[data-tour="jam-standards-select-song"]'
        view: multiple

tours:
    anchor-view-tour:
        once_key: anchor-view-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - jam-standards.*
        variants:
            all:
                steps:
                    - title: 'String anchor'
                      body: 'String anchor should default to individual view.'
                      target: test-anchor-string
                    - title: 'Multiple anchor'
                      body: 'Object anchor should preserve custom view mode.'
                      target: test-anchor-multiple
YAML);

    try {
        $payload = app(FeatureTourConfig::class)->payloadForRequest(User::factory()->make());

        expect($payload['anchors']['test-anchor-string']['view'] ?? null)->toBe('individual')
            ->and($payload['anchors']['test-anchor-multiple']['view'] ?? null)->toBe('multiple');
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects unsupported anchor view mode', function () {
    $path = resource_path('tours/001-invalid-anchor-view-test.yaml');

    File::put($path, <<<'YAML'
version: 1

anchors:
    bad-anchor:
        selector: '[data-tour="jam-standards-search"]'
        view: diagonal

tours:
    bad-anchor-view-tour:
        once_key: bad-anchor-view-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - jam-standards.*
        variants:
            all:
                steps:
                    - title: 'Bad view'
                      body: 'Invalid anchor view should fail validation.'
                      target: bad-anchor
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('unsupported view [diagonal]');
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts untargeted steps', function () {
    $path = resource_path('tours/001-untargeted-step-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    untargeted-step-tour:
        once_key: untargeted-step-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Welcome'
                      body: 'This introduction has no target.'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts after actions and validates their references', function () {
    $path = resource_path('tours/001-after-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    reveal-details:
        type: click
        target: '#after-action-target'

tours:
    after-action-tour:
        once_key: after-action-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'After action'
                      body: 'This step runs an action after the tour step settles.'
                      target: '#after-action-target'
                      after:
                        - reveal-details
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves after actions', function () {
    $path = resource_path('tours/001-after-action-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    reveal-details:
        type: click
        target: '#after-action-target'

tours:
    after-action-payload-tour:
        once_key: after-action-payload-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'After action payload'
                      body: 'Payload should preserve after actions.'
                      target: '#after-action-target'
                      after:
                        - reveal-details
YAML);

    try {
        $config = app(FeatureTourConfig::class)->mergedConfig();
        $steps = $config['tours']['after-action-payload-tour']['variants']['default']['steps'] ?? [];

        expect($steps)->toHaveCount(1)
            ->and($steps[0]['after'] ?? null)->toBe(['reveal-details']);
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects unknown after actions', function () {
    $path = resource_path('tours/001-invalid-after-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    invalid-after-action-tour:
        once_key: invalid-after-action-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid after action'
                      body: 'Unknown after action should fail validation.'
                      target: '#after-action-target'
                      after:
                        - missing-after-action
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('references unknown action [missing-after-action] in [after]');
    } finally {
        File::delete($path);
    }
});

test('feature tour validator accepts next and back actions', function () {
    $path = resource_path('tours/001-next-back-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    prepare-next:
        type: click
        target: '#next-target'
    prepare-back:
        type: click
        target: '#back-target'

tours:
    next-back-action-tour:
        once_key: next-back-action-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Hooked step'
                      body: 'This step reacts to next and back presses.'
                      target: '#next-target'
                      next:
                        - prepare-next
                      back:
                        - prepare-back
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');

        expect($exitCode)->toBe(0);
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves next and back actions', function () {
    $path = resource_path('tours/001-next-back-action-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

actions:
    prepare-next:
        type: click
        target: '#next-target'
    prepare-back:
        type: click
        target: '#back-target'

tours:
    next-back-action-payload-tour:
        once_key: next-back-action-payload-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Hook payload'
                      body: 'Payload should preserve next/back actions.'
                      target: '#next-target'
                      next:
                        - prepare-next
                      back:
                        - prepare-back
YAML);

    try {
        $config = app(FeatureTourConfig::class)->mergedConfig();
        $steps = $config['tours']['next-back-action-payload-tour']['variants']['default']['steps'] ?? [];

        expect($steps)->toHaveCount(1)
            ->and($steps[0]['next'] ?? null)->toBe(['prepare-next'])
            ->and($steps[0]['back'] ?? null)->toBe(['prepare-back']);
    } finally {
        File::delete($path);
    }
});

test('feature tour validator rejects unknown next and back actions', function () {
    $path = resource_path('tours/001-invalid-next-back-action-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    invalid-next-back-action-tour:
        once_key: invalid-next-back-action-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Invalid hooks'
                      body: 'Unknown next/back actions should fail validation.'
                      target: '#hook-target'
                      next:
                        - missing-next-action
                      back:
                        - missing-back-action
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('references unknown action [missing-next-action] in [next]')
            ->and($output)->toContain('references unknown action [missing-back-action] in [back]');
    } finally {
        File::delete($path);
    }
});

test('feature tour payload preserves untargeted steps', function () {
    $path = resource_path('tours/001-untargeted-payload-test.yaml');

    File::put($path, <<<'YAML'
version: 1

tours:
    untargeted-payload-tour:
        once_key: untargeted-payload-tour-v1
        authenticated: true
        trigger:
            mode: info-icon
        routes:
            - dashboard
        variants:
            default:
                steps:
                    - title: 'Intro'
                      body: 'No target for this step.'
                    - title: 'Targeted step'
                      body: 'This one uses a target.'
                      target: '#payload-target-example'
YAML);

    try {
        $config = app(FeatureTourConfig::class)->mergedConfig();
        $steps = $config['tours']['untargeted-payload-tour']['variants']['default']['steps'] ?? [];

        expect($steps)->toHaveCount(2)
            ->and(array_key_exists('target', $steps[0]))->toBeFalse()
            ->and($steps[1]['target'] ?? null)->toBe('#payload-target-example');
    } finally {
        File::delete($path);
    }
});

test('feature tour config loader merges files in filename order', function () {
    $firstPath = resource_path('tours/001-merge-test.yaml');
    $secondPath = resource_path('tours/002-merge-test.yaml');

    File::put($firstPath, <<<'YAML'
version: 1

anchors:
    merge-test-anchor: '#first'

tours:
    merge-test-tour:
        once_key: merge-test-v1
        authenticated: false
        priority: 150
        trigger:
            mode: auto
        routes:
            - dashboard
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'First file title'
                      body: 'First file body'
                      target: merge-test-anchor
YAML);

    File::put($secondPath, <<<'YAML'
version: 1

anchors:
    merge-test-anchor: '#second'

tours:
    merge-test-tour:
        once_key: merge-test-v2
        authenticated: true
        priority: 25
        trigger:
            mode: auto
        routes:
            - sessions.*
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'Second file title'
                      body: 'Second file body'
                      target: merge-test-anchor
YAML);

    try {
        $config = app(FeatureTourConfig::class)->mergedConfig();

        expect($config['anchors']['merge-test-anchor'] ?? null)->toBe('#second')
            ->and($config['tours']['merge-test-tour']['once_key'] ?? null)->toBe('merge-test-v2')
            ->and($config['tours']['merge-test-tour']['priority'] ?? null)->toBe(25)
            ->and($config['tours']['merge-test-tour']['routes'] ?? [])->toBe(['sessions.*'])
            ->and($config['tours']['merge-test-tour']['variants']['desktop']['steps'][0]['title'] ?? null)->toBe('Second file title');
    } finally {
        File::delete($firstPath);
        File::delete($secondPath);
    }
});

test('feature tour config validator can dump the merged config', function () {
    $firstPath = resource_path('tours/001-dump-test.yaml');
    $secondPath = resource_path('tours/002-dump-test.yaml');

    File::put($firstPath, <<<'YAML'
version: 1

tours:
    dump-test-tour:
        once_key: dump-test-v1
        trigger:
            mode: auto
        routes:
            - dashboard
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'First dump title'
                      body: 'First dump body'
                      target: '#first-dump-target'
YAML);

    File::put($secondPath, <<<'YAML'
version: 1

tours:
    dump-test-tour:
        once_key: dump-test-v2
        trigger:
            mode: auto
        routes:
            - sessions.*
        variants:
            desktop:
                media_query: '(min-width: 640px)'
                steps:
                    - title: 'Second dump title'
                      body: 'Second dump body'
                      target: '#second-dump-target'
YAML);

    try {
        $exitCode = Artisan::call('app:validate-feature-tours-config', [
            '--dump-config' => true,
        ]);
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Merged config:')
            ->and($output)->toContain('dump-test-v2')
            ->and($output)->toContain('Second dump title');
    } finally {
        File::delete($firstPath);
        File::delete($secondPath);
    }
});
