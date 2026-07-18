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
