<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notifications seed command creates notifications and can mark some seen', function () {
    $user = User::factory()->create();

    $this->artisan('notifications:seed-test', [
        'user' => (string) $user->id,
        '--count' => 8,
        '--seen' => 3,
    ])
        ->expectsOutputToContain('Created 8 test notifications')
        ->assertSuccessful();

    expect($user->notifications()->count())->toBe(8);
    expect($user->notifications()->whereNotNull('read_at')->count())->toBe(3);
    expect($user->notifications()->whereNull('dismissed_at')->count())->toBe(8);
});

test('notifications seed command fails for unknown user', function () {
    $this->artisan('notifications:seed-test', [
        'user' => 'missing-user@example.test',
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
