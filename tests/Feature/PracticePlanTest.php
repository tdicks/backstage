<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('practice plan requires authentication', function () {
    $this->get(route('practice-plan.index'))
        ->assertRedirect(route('login'));
});

test('practice plan lists songs where the user has assigned slots', function () {
    $user = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Practice Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::query()->create([
        'name' => 'Practice Set',
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Practice Artist',
        'title' => 'Practice Song',
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'guitar',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('practice-plan.index'))
        ->assertOk()
        ->assertSee('Practice Plan')
        ->assertSee('Practice Set')
        ->assertSee('Practice Artist - Practice Song')
        ->assertSee('Guitar');
});
