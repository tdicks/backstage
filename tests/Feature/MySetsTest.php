<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('my sets page requires authentication', function () {
    $this->get(route('my-sets.index'))
        ->assertRedirect(route('login'));
});

test('my sets shows upcoming and performed headings with planned and session groupings', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Upcoming Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    Set::query()->create([
        'name' => 'Planned Draft',
        'owner_id' => $user->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
    ]);

    Set::query()->create([
        'name' => 'Upcoming Scheduled',
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 2,
        'performed' => false,
        'is_hidden' => false,
    ]);

    Set::query()->create([
        'name' => 'Performed Set',
        'owner_id' => $other->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_PERFORMED,
        'position' => 3,
        'performed' => true,
        'is_hidden' => false,
    ]);

    $this->actingAs($user)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('My Sets')
        ->assertSee('Upcoming')
        ->assertSee('Performed')
        ->assertSee('Planned')
        ->assertSee('Upcoming Session')
        ->assertSee('Planned Draft')
        ->assertSee('Upcoming Scheduled')
        ->assertSee('Performed Set');
});

test('upcoming includes sets where user has one or more slots even when not owner or collaborator', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Slot Session',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $set = Set::query()->create([
        'name' => 'Slot Included Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Song',
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'guitar',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('Slot Included Set');
});

test('my sets count endpoint returns combined approval count for owner and collaborator sets', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Count Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::query()->create([
        'name' => 'Count Set',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
        'collaborator_ids' => [],
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Count Artist',
        'title' => 'Count Song',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $owner->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    SongRequest::query()->create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Request Artist',
        'title' => 'Request Song',
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->getJson(route('my-sets.count'))
        ->assertOk()
        ->assertJson(['count' => 3]);
});

test('my sets hides hidden sets when user is not owner or collaborator and has no slots', function () {
    $viewer = User::factory()->create();
    $owner = User::factory()->create();

    Set::query()->create([
        'name' => 'Hidden Set',
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 1,
        'performed' => false,
        'is_hidden' => true,
    ]);

    $this->actingAs($viewer)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertDontSee('Hidden Set');
});
