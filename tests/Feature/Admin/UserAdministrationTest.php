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

test('admin can update a user email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['email' => 'new@example.com'])
        ->assertRedirect();

    expect($user->refresh())->email->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
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