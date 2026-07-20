<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helpers

function makeSession(): JamSession
{
    return JamSession::query()->create([
        'name' => 'Collab Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);
}

function makeSet(User $owner, JamSession $session, array $collaboratorIds = []): Set
{
    return Set::query()->create([
        'name' => 'Collab Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
        'collaborator_ids' => $collaboratorIds ?: null,
    ]);
}

// ------- Model / migration tests -------

test('set model stores and retrieves collaborator_ids', function () {
    $owner = User::factory()->create();
    $collab1 = User::factory()->create();
    $collab2 = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab1->id, $collab2->id]);

    $set->refresh();

    expect($set->collaboratorUserIds())->toEqualCanonicalizing([$collab1->id, $collab2->id]);
    expect($set->isCollaborator($collab1))->toBeTrue();
    expect($set->isCollaborator($collab2))->toBeTrue();
    expect($set->isCollaborator($owner))->toBeFalse();
});

test('isCollaborator returns false when collaborator_ids is null', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    expect($set->isCollaborator($other))->toBeFalse();
});

// ------- SetCollaboratorController -------

test('set owner can update collaborators', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $this->actingAs($owner)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$collab->id],
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => $collab->name]);

    expect($set->fresh()->isCollaborator($collab))->toBeTrue();
});

test('admin can update collaborators on any set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $this->actingAs($admin)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$collab->id],
        ])
        ->assertOk();

    expect($set->fresh()->isCollaborator($collab))->toBeTrue();
});

test('collaborator cannot update collaborators', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();
    $other = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $this->actingAs($collab)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$other->id],
        ])
        ->assertForbidden();
});

test('unrelated user cannot update collaborators', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $this->actingAs($stranger)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$stranger->id],
        ])
        ->assertForbidden();
});

test('set owner is silently excluded if provided as a collaborator', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $this->actingAs($owner)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$owner->id, $collab->id],
        ])
        ->assertOk();

    expect($set->fresh()->collaboratorUserIds())->not->toContain($owner->id);
    expect($set->fresh()->isCollaborator($collab))->toBeTrue();
});

test('collaborators can be cleared by sending an empty list', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $this->actingAs($owner)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [],
        ])
        ->assertOk();

    expect($set->fresh()->collaboratorUserIds())->toBeEmpty();
});

// ------- Collaborator user search endpoint -------

test('owner can search for collaborator candidates', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $matching = User::factory()->create(['name' => 'Alice Matching']);
    User::factory()->create(['name' => 'Bob NoMatch']);

    $session = makeSession();
    $set = makeSet($owner, $session);

    $response = $this->actingAs($owner)
        ->getJson(route('sets.collaborators.users', $set).'?q=Alice')
        ->assertOk();

    $names = collect($response->json('users'))->pluck('name');
    expect($names)->toContain('Alice Matching');
    expect($names)->not->toContain('Bob NoMatch');
});

test('owner is excluded from the collaborator user search results', function () {
    $owner = User::factory()->create(['name' => 'Owner Person']);
    $collab = User::factory()->create(['name' => 'Owner Other']);

    $session = makeSession();
    $set = makeSet($owner, $session);

    $response = $this->actingAs($owner)
        ->getJson(route('sets.collaborators.users', $set).'?q=Owner')
        ->assertOk();

    $ids = collect($response->json('users'))->pluck('id');
    expect($ids)->not->toContain($owner->id);
    expect($ids)->toContain($collab->id);
});

// ------- Policy: songs -------

test('collaborator can add a song to the set', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $this->actingAs($collab)
        ->postJson(route('songs.store', $set), [
            'artist' => 'Collaborator Artist',
            'title' => 'Collaborator Song',
        ])
        ->assertStatus(200);

    expect($set->fresh()->songs()->count())->toBe(1);
});

test('collaborator can update a song in the set', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Old Artist',
        'title' => 'Old Title',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->patchJson(route('songs.update', $song), [
            'artist' => 'New Artist',
            'title' => 'New Title',
        ])
        ->assertRedirect();

    expect($song->fresh()->artist)->toBe('New Artist');
});

test('collaborator can delete a song from the set', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Delete Artist',
        'title' => 'Delete Song',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->deleteJson(route('songs.destroy', $song))
        ->assertStatus(200);

    expect(Song::find($song->id))->toBeNull();
});

test('unrelated user cannot update a song', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $this->actingAs($stranger)
        ->patchJson(route('songs.update', $song), [
            'artist' => 'Hacked',
            'title' => 'Hacked',
        ])
        ->assertForbidden();
});

// ------- Policy: slots -------

test('collaborator can add a slot to a song', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->postJson(route('slots.store', $song), ['name' => 'vocals'])
        ->assertStatus(201);

    expect($song->fresh()->slots()->count())->toBe(1);
});

test('collaborator can delete a slot', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->deleteJson(route('slots.destroy', $slot))
        ->assertStatus(200);

    expect(Slot::find($slot->id))->toBeNull();
});

// ------- SlotController::take -------

test('collaborator can take a slot directly', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->postJson(route('slots.take', $slot))
        ->assertOk();

    expect($slot->fresh()->user_id)->toBe($collab->id);
});

test('non-collaborator cannot take a slot directly', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($stranger)
        ->postJson(route('slots.take', $slot))
        ->assertForbidden();
});

// ------- SlotAssignmentController::respond -------

test('collaborator can approve a slot request', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();
    $requester = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $assignment = SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($collab)
        ->patchJson(route('slot-assignments.respond', $assignment), ['status' => 'accepted'])
        ->assertOk();

    expect($assignment->fresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($slot->fresh()->user_id)->toBe($requester->id);
});

test('unrelated user cannot respond to a slot request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $stranger = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $assignment = SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($stranger)
        ->patchJson(route('slot-assignments.respond', $assignment), ['status' => 'accepted'])
        ->assertForbidden();
});

// ------- Set card rendering -------

test('collaborator sees set management menu items but not Edit Set', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Test Artist',
        'title' => 'Test Song',
        'position' => 1,
    ]);

    $this->actingAs($collab)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Add Song')
        ->assertSee('Add Slot')
        ->assertDontSee('Edit Set')
        ->assertDontSee('Manage Collaborators');
});

test('set owner sees Edit Set and Manage Collaborators in menu', function () {
    $owner = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Test Artist',
        'title' => 'Test Song',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Edit Set')
        ->assertSee('Manage Collaborators');
});

test('admin sees Edit Set and Manage Collaborators on another user\'s set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Test Artist',
        'title' => 'Test Song',
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Edit Set')
        ->assertSee('Manage Collaborators');
});

test('collaborator names appear as initial data in the set card', function () {
    $owner = User::factory()->create(['name' => 'Set Owner']);
    $collab = User::factory()->create(['name' => 'Alice Collab']);

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Alice Collab', false);
});

test('hidden set is visible to collaborator', function () {
    $owner = User::factory()->create();
    $collab = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session, [$collab->id]);
    $set->update(['is_hidden' => true]);

    $this->actingAs($collab)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Collab Set');
});

test('hidden set is not visible to non-collaborator', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $session = makeSession();
    $set = makeSet($owner, $session);
    $set->update(['is_hidden' => true]);

    $this->actingAs($stranger)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertDontSee('Collab Set');
});
