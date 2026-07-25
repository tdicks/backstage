<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logging out shows a confirmation before returning home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('logged-out'));

    $this->assertGuest();

    $this->get(route('logged-out'))
        ->assertOk()
        ->assertSee('You&apos;ve logged out', false)
        ->assertSee('You&apos;ll be redirected to the homepage in', false)
        ->assertSee(route('home'));
});
