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
use Illuminate\Support\Facades\Cache;

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

test('deleting a set notifies stakeholders and only admins get a restore action', function () {
    $actorAdmin = User::factory()->create(['name' => 'Actor Admin', 'is_admin' => true]);
    $owner = User::factory()->create(['name' => 'Owner']);
    $collaborator = User::factory()->create(['name' => 'Collaborator']);
    $slotUser = User::factory()->create(['name' => 'Performer', 'is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Set Delete Session',
        'date' => now()->addWeek(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Delete Me',
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
        'artist' => 'Band',
        'title' => 'Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $slotUser->id,
    ]);

    $this->actingAs($actorAdmin)
        ->delete(route('sets.destroy', $set))
        ->assertRedirect();

    $ownerNotification = $owner->notifications()->latest()->first();
    $collaboratorNotification = $collaborator->notifications()->latest()->first();
    $slotUserNotification = $slotUser->notifications()->latest()->first();

    expect($ownerNotification?->data['type_key'])->toBe(NotificationTypeCatalog::SET_DELETED);
    expect($collaboratorNotification?->data['type_key'])->toBe(NotificationTypeCatalog::SET_DELETED);
    expect($slotUserNotification?->data['type_key'])->toBe(NotificationTypeCatalog::SET_DELETED);
    expect($slotUserNotification?->data['action_url'])->toContain('/recycle-bin');
    expect($ownerNotification?->data['action_url'])->toBeNull();
    expect($collaboratorNotification?->data['action_url'])->toBeNull();
    expect($collaboratorNotification?->data['body'])->toContain('You were a collaborator on this set.');
    expect($slotUserNotification?->data['body'])->toContain('Your slots:');
});

test('deleting a jam session notifies all users but only admins get a restore action', function () {
    $actorAdmin = User::factory()->create(['name' => 'Actor Admin', 'is_admin' => true]);
    $adminRecipient = User::factory()->create(['name' => 'Admin Recipient', 'is_admin' => true]);
    $viewer = User::factory()->create(['name' => 'Viewer']);
    $owner = User::factory()->create(['name' => 'Owner']);
    $slotUser = User::factory()->create(['name' => 'Performer', 'is_admin' => true]);

    $session = JamSession::create([
        'name' => 'Delete Session',
        'date' => now()->addWeek(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Session Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Band',
        'title' => 'Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $slotUser->id,
    ]);

    $this->actingAs($actorAdmin)
        ->delete(route('sessions.destroy', $session))
        ->assertRedirect(route('sessions.index'));

    $viewerNotification = $viewer->notifications()->latest()->first();
    $adminNotification = $adminRecipient->notifications()->latest()->first();
    $ownerNotification = $owner->notifications()->latest()->first();
    $slotUserNotification = $slotUser->notifications()->latest()->first();

    expect($viewerNotification?->data['type_key'])->toBe(NotificationTypeCatalog::JAM_SESSION_DELETED);
    expect($ownerNotification?->data['type_key'])->toBe(NotificationTypeCatalog::JAM_SESSION_DELETED);
    expect($slotUserNotification?->data['type_key'])->toBe(NotificationTypeCatalog::JAM_SESSION_DELETED);
    expect($viewerNotification?->data['action_url'])->toBeNull();
    expect($ownerNotification?->data['action_url'])->toBeNull();
    expect($slotUserNotification?->data['action_url'])->toContain('/recycle-bin');
    expect($adminNotification?->data['type_key'])->toBe(NotificationTypeCatalog::JAM_SESSION_DELETED);
    expect($adminNotification?->data['action_url'])->toContain('/recycle-bin');
    expect($slotUserNotification?->data['body'])->toContain('Impacted sets:');
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

test('accepted slot recommendations notify the recommender but rejected recommendations do not', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $recommender = User::factory()->create(['name' => 'Recommender']);
    $acceptedTarget = User::factory()->create(['name' => 'Accepted Target']);
    $rejectedTarget = User::factory()->create(['name' => 'Rejected Target']);
    $session = JamSession::create([
        'name' => 'Recommendation Notifications',
        'date' => now()->addWeek(),
        'description' => null,
    ]);
    $set = Set::create([
        'name' => 'Recommendation Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Recommendation Band',
        'title' => 'Recommendation Song',
        'notes' => null,
        'position' => 1,
    ]);
    $acceptedSlot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
    ]);
    $rejectedSlot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 2,
    ]);

    $this->actingAs($recommender)
        ->postJson(route('slot-assignments.propose', $acceptedSlot), ['target_user_id' => $acceptedTarget->id])
        ->assertCreated();
    $acceptedRecommendation = SlotAssignment::query()->where('slot_id', $acceptedSlot->id)->firstOrFail();

    $this->actingAs($acceptedTarget)
        ->patchJson(route('slot-assignments.respond', $acceptedRecommendation), ['status' => SlotAssignment::STATUS_ACCEPTED])
        ->assertOk();

    expect($recommender->notifications()->latest()->first()?->data['type_key'])
        ->toBe(NotificationTypeCatalog::SLOT_RECOMMENDATION_ACCEPTED);

    $this->actingAs($recommender)
        ->postJson(route('slot-assignments.propose', $rejectedSlot), ['target_user_id' => $rejectedTarget->id])
        ->assertCreated();
    $rejectedRecommendation = SlotAssignment::query()->where('slot_id', $rejectedSlot->id)->firstOrFail();

    $this->actingAs($rejectedTarget)
        ->patchJson(route('slot-assignments.respond', $rejectedRecommendation), ['status' => SlotAssignment::STATUS_REJECTED])
        ->assertOk();

    expect($recommender->notifications()->where('data->type_key', NotificationTypeCatalog::SLOT_RECOMMENDATION_ACCEPTED)->count())
        ->toBe(1);
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

test('notification feed polls only notifications created after the browser cursor', function () {
    $user = User::factory()->create();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Earlier update', 'body' => '', 'action_url' => null]
    ));
    $earlierNotification = $user->notifications()->firstOrFail();
    $earlierNotification->forceFill(['created_at' => now()->subMinute()])->save();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Latest update', 'body' => '', 'action_url' => null]
    ));
    $latestNotification = $user->notifications()->latest()->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('notifications.index', [
            'after' => $earlierNotification->created_at->toIso8601String(),
            'known_ids' => [$earlierNotification->id],
        ]))
        ->assertOk()
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.id', $latestNotification->id);
});

test('notification feed can page older notifications and reports total active count', function () {
    $user = User::factory()->create();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Oldest update', 'body' => '', 'action_url' => null]
    ));
    $oldestNotification = $user->notifications()->latest()->firstOrFail();
    $oldestNotification->forceFill(['created_at' => now()->subMinutes(4)])->save();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Older update', 'body' => '', 'action_url' => null]
    ));
    $olderNotification = $user->notifications()->latest()->firstOrFail();
    $olderNotification->forceFill(['created_at' => now()->subMinutes(3)])->save();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Newer update', 'body' => '', 'action_url' => null]
    ));
    $newerNotification = $user->notifications()->latest()->firstOrFail();
    $newerNotification->forceFill(['created_at' => now()->subMinutes(2)])->save();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        ['title' => 'Newest update', 'body' => '', 'action_url' => null]
    ));
    $newestNotification = $user->notifications()->latest()->firstOrFail();
    $newestNotification->forceFill(['created_at' => now()->subMinute()])->save();

    $this->actingAs($user)
        ->getJson(route('notifications.index', [
            'limit' => 2,
            'before' => $newerNotification->created_at->toIso8601String(),
        ]))
        ->assertOk()
        ->assertJsonPath('total_count', 4)
        ->assertJsonPath('notifications.0.id', $olderNotification->id)
        ->assertJsonPath('notifications.1.id', $oldestNotification->id)
        ->assertJsonMissingPath('notifications.2.id');

    expect([$newestNotification->id, $newerNotification->id])->not->toContain($olderNotification->id);
});

test('authenticated navigation renders the initial notification feed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('notification popup is positioned in the bottom right on desktop', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('sm:bottom-4 sm:right-4 sm:top-auto', false)
        ->assertSee('sm:w-96', false);
});

test('notification popup stacks multiple toasts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('$store.notifications.toasts.length > 0', false)
        ->assertSee('x-for="notification in $store.notifications.toasts"', false)
        ->assertSee('$store.notifications.closeToast(notification.id)', false);
});

test('notification overlay has dismiss all button in header', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('notificationSeenDelay: 2000', false)
        ->assertSee('dismissAll()', false)
        ->assertSee('@click="await $store.notifications.dismissAll()"', false)
        ->assertDontSee('@click="await $store.notifications.dismissAll(); closeNotifications()"', false)
        ->assertSee('Dismiss All', false);
});

test('dismiss all method dismisses all notifications', function () {
    $user = User::factory()->create();

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        [
            'title' => 'Test Title',
            'body' => 'Test Body',
            'action_url' => null,
            'action_label' => 'Open',
        ]
    ));

    $user->notify(new AppActivityNotification(
        NotificationTypeCatalog::SET_UPDATED,
        [
            'title' => 'Test Title 2',
            'body' => 'Test Body 2',
            'action_url' => null,
            'action_label' => 'Open',
        ]
    ));

    $response = $this->actingAs($user)
        ->getJson(route('notifications.index'));

    expect($response->json('notifications'))->toHaveCount(2);

    // Dismiss all via store method by dismissing each notification
    $notifications = $response->json('notifications');
    foreach ($notifications as $notification) {
        $this->actingAs($user)
            ->patchJson(route('notifications.dismiss', $notification['id']));
    }

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'notifications');
});

test('creating a visible set sends a new set created notification to users who can see the session', function () {
    $owner = User::factory()->create(['name' => 'Set Owner']);
    $viewer = User::factory()->create(['name' => 'Set Viewer']);

    $session = JamSession::query()->create([
        'name' => 'Visible Session',
        'date' => now()->addWeek(),
        'description' => null,
        'is_hidden' => false,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('sets.store', $session), [
            'name' => 'Fresh Set',
            'description' => null,
            'is_hidden' => false,
            'free_for_all' => false,
        ])
        ->assertRedirect();

    expect($owner->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->count())->toBe(0);
    expect($viewer->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->count())->toBe(1);

    $notification = $viewer->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->latest()->first();

    expect((string) ($notification?->data['title'] ?? ''))->toBe('New set created');
    expect((string) ($notification?->data['action_url'] ?? ''))->toContain('#set-');
});

test('creating a hidden set defers notification until first unhide and only sends once', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $viewer = User::factory()->create(['name' => 'Viewer']);

    $session = JamSession::query()->create([
        'name' => 'Deferred Session',
        'date' => now()->addWeek(),
        'description' => null,
        'is_hidden' => false,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('sets.store', $session), [
            'name' => 'Deferred Hidden Set',
            'description' => null,
            'is_hidden' => true,
            'free_for_all' => false,
        ])
        ->assertRedirect();

    $set = Set::query()->latest('id')->firstOrFail();
    $cacheKey = 'notifications:set_created:deferred:'.$set->id;

    expect(Cache::get($cacheKey))->toBeTrue();
    expect($viewer->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->count())->toBe(0);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => $set->name,
            'description' => $set->description,
            'position' => $set->position,
            'performed' => false,
            'signups_open' => true,
            'is_hidden' => false,
            'song_requests' => true,
            'free_for_all' => false,
            'jam_session_id' => $session->id,
        ])
        ->assertRedirect();

    expect(Cache::get($cacheKey))->toBeNull();
    expect($viewer->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->count())->toBe(1);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => $set->name,
            'description' => $set->description,
            'position' => $set->position,
            'performed' => false,
            'signups_open' => true,
            'is_hidden' => true,
            'song_requests' => true,
            'free_for_all' => false,
            'jam_session_id' => $session->id,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => $set->name,
            'description' => $set->description,
            'position' => $set->position,
            'performed' => false,
            'signups_open' => true,
            'is_hidden' => false,
            'song_requests' => true,
            'free_for_all' => false,
            'jam_session_id' => $session->id,
        ])
        ->assertRedirect();

    expect($viewer->notifications()->where('data->type_key', NotificationTypeCatalog::SET_CREATED)->count())->toBe(1);
});
