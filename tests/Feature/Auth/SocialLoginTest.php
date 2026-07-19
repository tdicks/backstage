<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

test('guest can be redirected to a supported social provider', function () {
    Socialite::shouldReceive('driver->redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.com'));

    $this->get(route('social.redirect', 'google'))
        ->assertRedirect('https://accounts.google.com');
});

test('social callback creates a user and linked social account', function () {
    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Social Player',
            'email' => 'social@example.com',
            'avatar' => 'https://example.com/social.jpg',
        ]));

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('my-sets.index', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'social@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Social Player')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'provider_email' => 'social@example.com',
        'provider_name' => 'Social Player',
        'avatar_url' => 'https://example.com/social.jpg',
    ]);
});

test('social callback links an existing user by email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(SocialiteUser::fake([
            'id' => 'facebook-456',
            'name' => 'Existing Player',
            'email' => 'existing@example.com',
        ]));

    $this->get(route('social.callback', 'facebook'))
        ->assertRedirect(route('my-sets.index', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_id' => 'facebook-456',
    ]);
});

test('unsupported social providers are not routed', function () {
    $this->get(route('social.redirect', 'github'))
        ->assertNotFound();
});

test('social login setting hides buttons and blocks provider redirects', function () {
    Setting::query()->create([
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '0',
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Continue with Google')
        ->assertDontSee('Continue with Facebook');

    $this->get(route('social.redirect', 'google'))
        ->assertNotFound();
});
