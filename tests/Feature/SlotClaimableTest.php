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
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function claimableTestSlot(User $owner, User $assignee, bool $freeForAll = false): Slot
{
    $session = JamSession::query()->create([
        'name' => 'Claimable Jam',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Claimable Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
        'free_for_all' => $freeForAll,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Miles Davis',
        'title' => 'So What',
        'notes' => null,
    ]);

    return Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => $assignee->id,
        'is_claimable_manual' => false,
    ]);
}

test('assignee can mark an assigned slot claimable and pending requesters are notified', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $requesterA = User::factory()->create();
    $requesterB = User::factory()->create();

    $slot = claimableTestSlot($owner, $assignee);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requesterA->id,
        'target_user_id' => $requesterA->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requesterB->id,
        'target_user_id' => $requesterB->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $response = $this->actingAs($assignee)
        ->patchJson(route('slots.claimable', $slot), [
            'is_claimable_manual' => true,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('slot.is_claimable_manual', true)
        ->assertJsonPath('slot.is_claimable', true);

    expect($slot->refresh()->is_claimable_manual)->toBeTrue();

    Notification::assertSentTo(
        [$requesterA, $requesterB],
        AppActivityNotification::class,
        function (AppActivityNotification $notification, array $channels, User $notifiable) {
            return ($notification->toDatabase($notifiable)['type_key'] ?? null) === NotificationTypeCatalog::SLOT_REQUEST_CLAIMABLE;
        }
    );
});

test('claiming a claimable slot resets manual claimable and only clears claimers pending request', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $claimer = User::factory()->create();
    $otherRequester = User::factory()->create();

    $slot = claimableTestSlot($owner, $assignee, freeForAll: true);
    $slot->update(['is_claimable_manual' => true]);

    $claimersRequest = SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $claimer->id,
        'target_user_id' => $claimer->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $otherRequest = SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $otherRequester->id,
        'target_user_id' => $otherRequester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $response = $this->actingAs($claimer)
        ->postJson(route('slots.take', $slot), []);

    $response
        ->assertOk()
        ->assertJsonPath('slot.user_id', $claimer->id)
        ->assertJsonPath('slot.is_claimable_manual', false)
        ->assertJsonPath('slot.is_claimable', false);

    expect($slot->refresh()->user_id)->toBe($claimer->id)
        ->and($slot->is_claimable_manual)->toBeFalse()
        ->and($claimersRequest->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED)
        ->and($claimersRequest->responded_at)->not->toBeNull()
        ->and($otherRequest->refresh()->status)->toBe(SlotAssignment::STATUS_PENDING)
        ->and($otherRequest->responded_at)->toBeNull();
});

test('non assignee non manager cannot toggle slot claimable status', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $otherUser = User::factory()->create();

    $slot = claimableTestSlot($owner, $assignee);

    $this->actingAs($otherUser)
        ->patchJson(route('slots.claimable', $slot), [
            'is_claimable_manual' => true,
        ])
        ->assertForbidden();

    expect($slot->refresh()->is_claimable_manual)->toBeFalse();
});

test('manual claimable resets when slot is reassigned via slot update', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $replacement = User::factory()->create();

    $slot = claimableTestSlot($owner, $assignee);
    $slot->update(['is_claimable_manual' => true]);

    $response = $this->actingAs($owner)
        ->patchJson(route('slots.update', $slot), [
            'name' => $slot->name,
            'notes' => $slot->notes,
            'user_id' => $replacement->id,
            'manual_performer_name' => null,
            'position' => $slot->position,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('slot.user_id', $replacement->id)
        ->assertJsonPath('slot.is_claimable_manual', false)
        ->assertJsonPath('slot.is_claimable', false);

    expect($slot->refresh()->is_claimable_manual)->toBeFalse();
});

test('manual claimable resets when a pending request is accepted and assigned', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $requester = User::factory()->create();

    $slot = claimableTestSlot($owner, $assignee);
    $slot->update(['is_claimable_manual' => true]);

    $this->actingAs($requester)
        ->postJson(route('slot-assignments.request', $slot), [])
        ->assertCreated();

    $assignment = SlotAssignment::query()
        ->where('slot_id', $slot->id)
        ->where('actor_user_id', $requester->id)
        ->where('target_user_id', $requester->id)
        ->where('type', SlotAssignment::TYPE_REQUEST)
        ->where('status', SlotAssignment::STATUS_PENDING)
        ->latest('id')
        ->firstOrFail();

    $response = $this->actingAs($owner)
        ->patchJson(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('slot.user_id', $requester->id)
        ->assertJsonPath('slot.is_claimable_manual', false)
        ->assertJsonPath('slot.is_claimable', false);

    expect($slot->refresh()->user_id)->toBe($requester->id)
        ->and($slot->is_claimable_manual)->toBeFalse();
});
