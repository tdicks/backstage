<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('admin can search and sort users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin User', 'email' => 'admin@example.com']);
    User::factory()->create(['name' => 'Zoe Zebra', 'email' => 'zoe@example.com']);
    User::factory()->create(['name' => 'Alice Archer', 'email' => 'alice@example.com']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => 'alice', 'sort' => 'email', 'direction' => 'asc']))
        ->assertOk()
        ->assertSee('Alice Archer')
        ->assertSee('alice@example.com')
        ->assertDontSee('Zoe Zebra');
});

test('admin can update user details', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
        'bio' => 'Old bio',
        'hide_from_directory' => false,
        'hide_from_slot_proposals' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'bio' => 'New bio',
            'hide_from_directory' => 1,
            'hide_from_slot_proposals' => 1,
            'is_admin' => 1,
        ])
        ->assertRedirect();

    expect($user->refresh())->name->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->bio)->toBe('New bio');
    expect($user->hide_from_directory)->toBeTrue();
    expect($user->hide_from_slot_proposals)->toBeTrue();
    expect($user->is_admin)->toBeTrue();
});

test('admin cannot remove their own admin role through user update', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'bio' => $admin->bio,
            'hide_from_directory' => 0,
            'hide_from_slot_proposals' => 0,
            'is_admin' => 0,
        ])
        ->assertRedirect();

    expect($admin->refresh()->is_admin)->toBeTrue();
});

test('admin can send a password reset email', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'reset-me@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $user))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('admin can send a password reset email dynamically', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'dynamic-reset@example.com']);

    $this->actingAs($admin)
        ->postJson(route('admin.users.password-reset', $user))
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, ResetPassword::class);
});