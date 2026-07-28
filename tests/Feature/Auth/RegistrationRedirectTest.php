<?php

use App\Models\JamSession;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration from a jam register quick code returns to that page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::query()->create([
        'name' => 'Friday Jam',
        'date' => now()->toDateString(),
        'description' => null,
        'allow_checkins' => true,
        'jam_register_code' => 'Ab12',
    ]);
    $returnTo = route('jam-register.session', $session->jam_register_code);

    $this->get(route('register', ['return_to' => $returnTo]))
        ->assertOk();

    $this->post(route('register'), [
        'name' => 'New Player',
        'email' => 'new.player@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'return_to' => $returnTo,
    ])->assertRedirect($returnTo);

    $this->assertAuthenticated();
    expect($admin->notifications()->latest()->first()?->data['type_key'])
        ->toBe(NotificationTypeCatalog::ADMIN_USER_REGISTERED);
});
