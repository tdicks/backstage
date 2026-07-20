<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile update stores visibility preferences', function () {
    $user = User::factory()->create([
        'hide_from_directory' => false,
        'hide_from_slot_proposals' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => 'Updated bio',
            'hide_from_directory' => '1',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->hide_from_directory)->toBeTrue();
    expect($user->hide_from_slot_proposals)->toBeFalse();
});

test('profile update stores slot coverage', function () {
    $user = User::factory()->create(['slot_coverage' => null]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'slot_coverage' => ['vocals', 'bass'],
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->slot_coverage)->toBe(['vocals', 'bass']);
});

test('profile update clears slot coverage when none selected', function () {
    $user = User::factory()->create(['slot_coverage' => ['vocals', 'drums']]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->slot_coverage)->toBe([]);
});

test('profile edit highlights selected slot coverage chips', function () {
    $user = User::factory()->create(['slot_coverage' => ['vocals', 'bass']]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('x-data="{ selected: ', false)
        ->assertSee('x-bind:class="selected ? \'border-indigo-300 bg-indigo-50 text-indigo-700\'', false)
        ->assertSee('@change="selected = $event.target.checked"', false);
});
