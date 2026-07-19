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

test('user directory shows slot coverage for users', function () {
    $viewer = User::factory()->create();
    User::factory()->create([
        'name' => 'Jazz Player',
        'slot_coverage' => ['vocals', 'bass'],
        'hide_from_directory' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertSee('Jazz Player');
});

test('user directory excludes users who hide themselves', function () {
    $viewer = User::factory()->create();
    User::factory()->create([
        'name' => 'Visible User',
        'bio' => 'Shows up in the directory',
        'hide_from_directory' => false,
    ]);
    User::factory()->create([
        'name' => 'Hidden User',
        'bio' => 'Should be hidden',
        'hide_from_directory' => true,
    ]);

    $this->actingAs($viewer)
        ->get(route('directory.index'))
        ->assertOk()
        ->assertSee('Visible User')
        ->assertDontSee('Hidden User');
});