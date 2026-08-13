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
