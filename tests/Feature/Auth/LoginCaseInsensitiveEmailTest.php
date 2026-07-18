<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can log in with a mixed-case email address', function () {
    $user = User::factory()->create([
        'email' => 'test.user@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'TeSt.UsEr@Example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('my-sets.index', absolute: false));
    $this->assertAuthenticatedAs($user);
});
