<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('forgot password accepts mixed-case email and sends reset notification', function () {
    $user = User::factory()->create([
        'email' => 'test.user@example.com',
    ]);

    Notification::fake();

    $response = $this->post('/forgot-password', [
        'email' => 'TeSt.UsEr@Example.com',
    ]);

    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});

test('password reset accepts mixed-case email', function () {
    $user = User::factory()->create([
        'email' => 'test.user@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'TeSt.UsEr@Example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertRedirect(route('login'));

    expect(Hash::check('NewPassword123!', $user->refresh()->password))->toBeTrue();
});
