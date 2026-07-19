<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('about and privacy pages are public without the app navigation', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('About')
        ->assertSee('Backstage helps players organise jam sessions')
        ->assertDontSee('My Sets');

    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Backstage stores account and session information')
        ->assertDontSee('My Sets');
});

test('about and privacy pages use the app layout for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('about'))
        ->assertOk()
        ->assertSee('About')
        ->assertSee('My Sets')
        ->assertSee('Help');

    $this->actingAs($user)->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('My Sets')
        ->assertSee('Help');
});

test('help page is only available to authenticated users', function () {
    $this->get(route('help'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('help'))
        ->assertOk()
        ->assertSee('Adding Songs')
        ->assertSee('band template')
        ->assertSee('Set Requests and Approvals')
        ->assertSee('Recommendations');
});
