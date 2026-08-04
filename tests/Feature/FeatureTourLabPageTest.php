<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies non-admin users from the feature tour lab page', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('feature-tours.lab'))
        ->assertForbidden();
});

it('allows admin users to access the feature tour lab page', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('feature-tours.lab'))
        ->assertOk()
        ->assertSee('Feature Tour Lab')
        ->assertSee('Start Fallback Tour');
});
