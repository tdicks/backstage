<?php

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
        ->assertSee('Unlock layout')
        ->assertSee('Three quick steps')
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
