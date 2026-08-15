<?php

use App\Models\JamSession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the Backstage welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Make the next jam happen.')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('authenticated users visiting the homepage are redirected to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('guests visiting the dashboard are redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard gridstack page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('data-dashboard-gridstack', false)
        ->assertSee('data-gridstack-canvas', false)
        ->assertSee('aria-label="Unlock layout"', false)
        ->assertSee('data-gridstack-toggle-locked-icon', false)
        ->assertSee('Getting Started')
        ->assertSee('Approvals and Requests')
        ->assertSee('Shortcuts')
        ->assertSee('Sets that need players');
});

test('dashboard hides get started widget when onboarding has been dismissed', function () {
    $user = User::factory()->create([
        'onboarding_dismissed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Three quick steps')
        ->assertDontSee('gs-id="getting-started"', false)
        ->assertSee('Approvals and Requests');
});

test('dashboard shows the live session widget for a visible live session', function () {
    $user = User::factory()->create();
    $liveSession = JamSession::query()->create([
        'name' => 'Friday Live Jam',
        'date' => now(),
        'is_live' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('gs-id="live-session"', false)
        ->assertSee('Live now')
        ->assertSee($liveSession->name)
        ->assertSee(route('sessions.live.dashboard', $liveSession), false);
});

test('dashboard excludes the live session widget when no visible session is live', function () {
    $user = User::factory()->create();
    JamSession::query()->create([
        'name' => 'Hidden Live Jam',
        'date' => now(),
        'is_hidden' => true,
        'is_live' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('gs-id="live-session"', false);
});

test('dashboard loads initial layout from the user profile', function () {
    $user = User::factory()->create([
        'dashboard_widget_layouts' => [
            ['id' => 'coming-up', 'x' => 1, 'y' => 2, 'w' => 5, 'h' => 4],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-initial-layout-json=', false)
        ->assertSee('coming-up', false)
        ->assertSee('"x":1', false)
        ->assertSee('"y":2', false)
        ->assertSee('"w":5', false)
        ->assertSee('"h":4', false);
});

test('dashboard uses the captured default layout when the user has no saved layout', function () {
    Setting::query()->create([
        'key' => Setting::DASHBOARD_DEFAULT_WIDGET_LAYOUT_KEY,
        'name' => 'Default dashboard layout',
        'input_type' => 'textarea',
        'value' => json_encode([
            ['id' => 'quick-moves', 'x' => 3, 'y' => 2, 'w' => 5, 'h' => 2],
        ], JSON_THROW_ON_ERROR),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('"quick-moves","x":3,"y":2,"w":5,"h":2', false);
});

test('dashboard keeps a user saved layout instead of the captured default', function () {
    Setting::query()->create([
        'key' => Setting::DASHBOARD_DEFAULT_WIDGET_LAYOUT_KEY,
        'name' => 'Default dashboard layout',
        'input_type' => 'textarea',
        'value' => json_encode([
            ['id' => 'quick-moves', 'x' => 3, 'y' => 2, 'w' => 5, 'h' => 2],
        ], JSON_THROW_ON_ERROR),
    ]);
    $user = User::factory()->create([
        'dashboard_widget_layouts' => [
            ['id' => 'quick-moves', 'x' => 1, 'y' => 4, 'w' => 6, 'h' => 2],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('"quick-moves","x":1,"y":4,"w":6,"h":2', false)
        ->assertDontSee('"quick-moves","x":3,"y":2,"w":5,"h":2', false);
});
test('dashboard layout updates are saved to the authenticated user profile', function () {
    $user = User::factory()->create();

    $payload = [
        'layout' => [
            ['id' => 'quick-moves', 'x' => 2, 'y' => 3, 'w' => 6, 'h' => 2],
            ['id' => 'action-inbox', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('dashboard.layout.save'), $payload)
        ->assertNoContent();

    $user->refresh();

    expect($user->dashboard_widget_layouts)->toBe($payload['layout']);
});

test('dashboard layout saves the live session widget position while a live session is visible', function () {
    $user = User::factory()->create();
    JamSession::query()->create([
        'name' => 'Friday Live Jam',
        'date' => now(),
        'is_live' => true,
    ]);
    $payload = [
        'layout' => [
            ['id' => 'live-session', 'x' => 5, 'y' => 2, 'w' => 4, 'h' => 3],
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('dashboard.layout.save'), $payload)
        ->assertNoContent();

    expect($user->refresh()->dashboard_widget_layouts)->toBe($payload['layout']);
});
