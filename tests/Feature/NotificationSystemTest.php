<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use App\Notifications\AppActivityNotification;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('collaborator updates notify added and removed users but not the actor', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $added = User::factory()->create(['name' => 'Added']);
    $removed = User::factory()->create(['name' => 'Removed']);

    $session = JamSession::create([
        'name' => 'Collaborator Notifications',
        'date' => now()->addWeek(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Collab Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
        'collaborator_ids' => [$removed->id],
    ]);

    $this->actingAs($owner)
        ->putJson(route('sets.collaborators.update', $set), [
            'collaborator_ids' => [$added->id],
        ])
        ->assertOk();

    expect($owner->notifications()->count())->toBe(0);
    expect($added->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SET_COLLABORATOR_ADDED);
    expect($removed->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SET_COLLABORATOR_REMOVED);
});

test('slot requests notify set managers and accepted requests notify the requester', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $collaborator = User::factory()->create(['name' => 'Collaborator']);
    $requester = User::factory()->create(['name' => 'Requester']);

    $session = JamSession::create([
        'name' => 'Slot Notifications',
        'date' => now()->addWeek(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Slot Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
        'collaborator_ids' => [$collaborator->id],
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'The Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->postJson(route('slot-assignments.request', $slot), [])
        ->assertCreated();

    $assignment = SlotAssignment::query()->firstOrFail();

    expect($owner->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SLOT_REQUEST_RECEIVED);
    expect($collaborator->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SLOT_REQUEST_RECEIVED);
    expect($requester->notifications()->count())->toBe(0);

    $this->actingAs($owner)
        ->patchJson(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertOk();

    expect($requester->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SLOT_REQUEST_ACCEPTED);
});

test('notification feed excludes dismissed items and can mark notifications seen', function () {
    $user = User::factory()->create();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        [
            'title' => 'Set updated',
            'body' => 'Something changed.',
            'action_url' => null,
            'action_label' => 'Open',
        ]
    ));

    $notification = $user->notifications()->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.id', $notification->id);

    $this->actingAs($user)
        ->patchJson(route('notifications.seen', $notification->id))
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('notifications.dismiss', $notification->id))
        ->assertOk();

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'notifications');
});
