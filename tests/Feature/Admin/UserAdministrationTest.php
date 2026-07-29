<?php

use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'slot_coverage' => ['vocals', 'drums'],
            'is_admin' => 1,
        ])
        ->assertRedirect();

    expect($user->refresh())->name->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->bio)->toBe('New bio');
    expect($user->hide_from_directory)->toBeTrue();
    expect($user->hide_from_slot_proposals)->toBeTrue();
    expect($user->slot_coverage)->toBe(['vocals', 'drums']);
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
