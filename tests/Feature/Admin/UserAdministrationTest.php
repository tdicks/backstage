<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('admin can search and sort users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin User', 'email' => 'admin@example.com']);
    User::factory()->create(['name' => 'Zoe Zebra', 'email' => 'zoe@example.com']);
    User::factory()->create(['name' => 'Alice Archer', 'email' => 'alice@example.com']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => 'alice', 'sort' => 'email', 'direction' => 'asc']))
        ->assertOk()
        ->assertSee('Alice Archer')
        ->assertSee('alice@example.com')
        ->assertDontSee('Zoe Zebra');
});

test('user administration uses a compact mobile table layout', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('w-full table-fixed divide-y divide-slate-200 md:table-auto', false)
        ->assertSee('hidden px-6 py-3 md:table-cell', false)
        ->assertSee('break-all px-3 py-4 text-sm text-slate-700 md:px-6', false);
});

test('admin can update user details', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
        'bio' => 'Old bio',
        'hide_from_directory' => false,
        'hide_from_slot_proposals' => false,
        'slot_coverage' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'bio' => 'New bio',
            'hide_from_directory' => 1,
            'hide_from_slot_proposals' => 1,
            'slot_coverage' => [
                'vocals' => 'can',
                'drums' => 'wont_cover',
            ],
            'is_admin' => 1,
        ])
        ->assertRedirect();

    expect($user->refresh())->name->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->bio)->toBe('New bio');
    expect($user->hide_from_directory)->toBeTrue();
    expect($user->hide_from_slot_proposals)->toBeTrue();
    expect($user->slot_coverage)->toBe([
        'vocals' => 'can',
        'drums' => 'wont_cover',
    ]);
    expect($user->is_admin)->toBeTrue();
});

test('admin cannot remove their own admin role through user update', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'bio' => $admin->bio,
            'hide_from_directory' => 0,
            'hide_from_slot_proposals' => 0,
            'is_admin' => 0,
        ])
        ->assertRedirect();

    expect($admin->refresh()->is_admin)->toBeTrue();
});

test('admin can send a password reset email', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'reset-me@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $user))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('admin can send a password reset email dynamically', function () {
    Notification::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['email' => 'dynamic-reset@example.com']);

    $this->actingAs($admin)
        ->postJson(route('admin.users.password-reset', $user))
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('promoting a user to admin notifies the target first and then other current admins', function () {
    $actor = User::factory()->create(['is_admin' => true, 'name' => 'Grantor']);
    $otherAdmin = User::factory()->create(['is_admin' => true, 'name' => 'Other Admin']);
    $target = User::factory()->create(['is_admin' => false, 'name' => 'New Admin']);

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'bio' => $target->bio,
            'hide_from_directory' => 0,
            'hide_from_slot_proposals' => 0,
            'is_admin' => 1,
        ])
        ->assertRedirect();

    expect($target->refresh()->is_admin)->toBeTrue();

    $targetTypeKeys = $target->notifications()
        ->get()
        ->map(fn ($notification) => $notification->data['type_key'])
        ->all();
    expect($targetTypeKeys)->toContain(NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_GRANTED)
        ->not->toContain(NotificationTypeCatalog::ADMIN_ACCESS_GRANTED_TO_USER);

    $actorAdminNotice = $actor->notifications()->latest()->first();
    expect($actorAdminNotice?->data['type_key'])->toBe(NotificationTypeCatalog::ADMIN_ACCESS_GRANTED_TO_USER);
    expect($actorAdminNotice?->data['body'])->toContain('New Admin')->toContain('Grantor');

    $otherAdminNotice = $otherAdmin->notifications()->latest()->first();
    expect($otherAdminNotice?->data['type_key'])->toBe(NotificationTypeCatalog::ADMIN_ACCESS_GRANTED_TO_USER);
    expect($otherAdminNotice?->data['body'])->toContain('New Admin')->toContain('Grantor');
});

test('revoking admin access notifies the target and remaining admins, excluding the demoted user from admin broadcast', function () {
    $actor = User::factory()->create(['is_admin' => true, 'name' => 'Revoker']);
    $otherAdmin = User::factory()->create(['is_admin' => true, 'name' => 'Other Admin']);
    $target = User::factory()->create(['is_admin' => true, 'name' => 'Former Admin']);

    $this->actingAs($actor)
        ->patch(route('admin.users.toggle-role', $target))
        ->assertRedirect();

    expect($target->refresh()->is_admin)->toBeFalse();

    $targetTypeKeys = $target->notifications()
        ->get()
        ->map(fn ($notification) => $notification->data['type_key'])
        ->all();
    expect($targetTypeKeys)->toContain(NotificationTypeCatalog::ACCOUNT_ADMIN_ACCESS_REVOKED)
        ->not->toContain(NotificationTypeCatalog::ADMIN_ACCESS_REVOKED_FROM_USER);

    $actorAdminNotice = $actor->notifications()->latest()->first();
    expect($actorAdminNotice?->data['type_key'])->toBe(NotificationTypeCatalog::ADMIN_ACCESS_REVOKED_FROM_USER);
    expect($actorAdminNotice?->data['body'])->toContain('Former Admin')->toContain('Revoker');

    $otherAdminNotice = $otherAdmin->notifications()->latest()->first();
    expect($otherAdminNotice?->data['type_key'])->toBe(NotificationTypeCatalog::ADMIN_ACCESS_REVOKED_FROM_USER);
    expect($otherAdminNotice?->data['body'])->toContain('Former Admin')->toContain('Revoker');
});

test('manual slot transfer dataset includes only current open non-archived jam sessions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $matchingUser = User::factory()->create(['name' => 'Alex Stone']);
    User::factory()->create(['name' => 'Marta Jones']);

    $eligibleSession = JamSession::create([
        'name' => 'Eligible Session',
        'date' => now()->addDay(),
        'description' => null,
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $pastSession = JamSession::create([
        'name' => 'Past Session',
        'date' => now()->subDay(),
        'description' => null,
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $closedSession = JamSession::create([
        'name' => 'Closed Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => true,
        'is_archived' => false,
    ]);

    $archivedSession = JamSession::create([
        'name' => 'Archived Session',
        'date' => now()->addDays(3),
        'description' => null,
        'is_closed' => false,
        'is_archived' => true,
    ]);

    $eligibleSet = Set::create([
        'name' => 'Eligible Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $eligibleSession->id,
        'position' => 1,
    ]);
    $pastSet = Set::create([
        'name' => 'Past Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $pastSession->id,
        'position' => 1,
    ]);
    $closedSet = Set::create([
        'name' => 'Closed Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $closedSession->id,
        'position' => 1,
    ]);
    $archivedSet = Set::create([
        'name' => 'Archived Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $archivedSession->id,
        'position' => 1,
    ]);

    $eligibleSong = Song::create([
        'set_id' => $eligibleSet->id,
        'artist' => 'The Band',
        'title' => 'Current Song',
        'position' => 1,
    ]);

    $pastSong = Song::create([
        'set_id' => $pastSet->id,
        'artist' => 'The Band',
        'title' => 'Past Song',
        'position' => 1,
    ]);

    $closedSong = Song::create([
        'set_id' => $closedSet->id,
        'artist' => 'The Band',
        'title' => 'Closed Song',
        'position' => 1,
    ]);

    $archivedSong = Song::create([
        'set_id' => $archivedSet->id,
        'artist' => 'The Band',
        'title' => 'Archived Song',
        'position' => 1,
    ]);

    $eligibleSlot = Slot::create([
        'song_id' => $eligibleSong->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Alex Stone',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $pastSong->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Alex Stone',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $closedSong->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Alex Stone',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $archivedSong->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Alex Stone',
        'position' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.users.manual-slot-transfers.index'))
        ->assertOk();

    expect($response->json('slots'))->toHaveCount(1);
    expect($response->json('slots.0.slot_id'))->toBe($eligibleSlot->id);
    expect($response->json('slots.0.user_options.0.id'))->toBe($matchingUser->id);
});

test('admin can transfer a manual slot assignment to a user and notify them', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Transfer']);
    $target = User::factory()->create(['name' => 'Target Player']);

    $session = JamSession::create([
        'name' => 'Transfer Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $set = Set::create([
        'name' => 'Transfer Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Transfer Artist',
        'title' => 'Transfer Song',
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'manual_performer_name' => 'Target Player',
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.users.manual-slot-transfers.apply'), [
            'changes' => [
                [
                    'slot_id' => $slot->id,
                    'user_id' => $target->id,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.slot_id', $slot->id)
        ->assertJsonPath('results.0.status', 'updated');

    expect($slot->refresh()->user_id)->toBe($target->id);
    expect($slot->manual_performer_name)->toBeNull();

    $notification = $target->notifications()->latest()->first();
    expect($notification?->data['type_key'])->toBe(NotificationTypeCatalog::SLOT_MANUAL_ASSIGNMENT_TRANSFERRED);
    expect($notification?->data['title'])->toContain('Manual slot assignment transferred');
});

test('manual slot transfer endpoints are restricted to admins', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.users.manual-slot-transfers.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.users.manual-slot-transfers.apply'), [
            'changes' => [],
        ])
        ->assertForbidden();
});

test('newly registered users are surfaced in manual slot transfer suggestions', function () {
    Event::fakeExcept(Registered::class);

    $admin = User::factory()->create(['is_admin' => true]);
    $session = JamSession::create([
        'name' => 'Suggestion Session',
        'date' => now()->addDays(2),
        'description' => null,
        'is_closed' => false,
        'is_archived' => false,
    ]);

    $set = Set::create([
        'name' => 'Suggestion Set',
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Suggestion Artist',
        'title' => 'Suggestion Song',
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'manual_performer_name' => 'Taylor Casey',
        'position' => 1,
    ]);

    $registeredUser = User::factory()->create(['name' => 'Taylor Casey']);
    event(new Registered($registeredUser));

    $response = $this->actingAs($admin)
        ->getJson(route('admin.users.manual-slot-transfers.index'))
        ->assertOk();

    expect($response->json('slots.0.user_options.0.id'))->toBe($registeredUser->id);
});
