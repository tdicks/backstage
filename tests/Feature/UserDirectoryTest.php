<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user directory is searchable by name and bio', function () {
    $viewer = User::factory()->create();
    User::factory()->create(['name' => 'Alice Wonder', 'bio' => 'Loves funk bass lines']);
    User::factory()->create(['name' => 'Bobby Drums', 'bio' => 'Pocket drummer']);

    $this->actingAs($viewer)
        ->get(route('directory.index', ['q' => 'funk']))
        ->assertOk()
        ->assertSee('User Directory')
        ->assertSee('Alice Wonder')
        ->assertSee('Loves funk bass lines')
        ->assertDontSee('Bobby Drums');
});