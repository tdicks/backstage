<?php

use App\Models\User;
use App\Support\NotificationTypeCatalog;
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
            'slot_coverage_present' => '1',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->slot_coverage)->toBe([]);
});

test('profile update stores mobile number and notification preferences', function () {
    $user = User::factory()->create([
        'mobile_number' => null,
        'notification_preferences' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'mobile_number' => '+447700900123',
            'notification_preferences' => [
                'slot_request_received' => [
                    'enabled' => '0',
                    'popup' => '1',
                    'email' => '0',
                    'push' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->mobile_number)->toBe('+447700900123');
    expect($user->notification_preferences['slot_request_received']['enabled'])->toBeFalse();
    expect($user->notification_preferences['slot_request_received']['popup'])->toBeTrue();
    expect($user->notification_preferences['slot_request_received']['email'])->toBeFalse();
    expect($user->notification_preferences['slot_request_received']['push'])->toBeTrue();
});

test('profile page shows notification preferences section', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Notification Preferences')
        ->assertSee('Apply to all')
        ->assertSee('Enable Push Notifications');
});

test('admin profile shows the admin notification group with blue admin styling', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Admin')
        ->assertSee('border-sky-400', false)
        ->assertSee('h-4 w-4 text-sky-500', false);
});

test('profile edit highlights selected slot coverage chips', function () {
    $user = User::factory()->create(['slot_coverage' => ['vocals', 'bass']]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Notification Preferences')
        ->assertSee('Mobile Number')
        ->assertSee('Slot request accepted')
        ->assertSee('x-data="{ selected: ', false)
        ->assertSee('x-bind:class="selected ? \'border-indigo-300 bg-indigo-50 text-indigo-700\'', false)
        ->assertSee('@change="selected = $event.target.checked"', false);
});

test('profile update ignores non-user-configurable notification preference changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'notification_preferences' => [
                NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED => [
                    'enabled' => '0',
                    'popup' => '0',
                    'email' => '0',
                    'push' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('profile.edit'));

    $stored = $user->refresh()->notification_preferences;

    expect($stored[NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED]['enabled'])->toBeTrue();
    expect($stored[NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED]['popup'])->toBeTrue();
    expect($stored[NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED]['email'])->toBeTrue();
    expect($stored[NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED]['push'])->toBeFalse();
});
