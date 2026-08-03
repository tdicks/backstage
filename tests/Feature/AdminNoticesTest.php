<?php

use App\Models\JamSession;
use App\Models\Notice;
use App\Models\NoticeDismissal;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view notices administration page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.notices.index'))
        ->assertOk()
        ->assertSee('App Notices')
        ->assertSee('Create Notice');
});

test('non admin cannot access notices administration page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.notices.index'))
        ->assertForbidden();
});

test('admin can create update and delete notices dynamically', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $createResponse = $this->actingAs($admin)
        ->postJson(route('admin.notices.store'), [
            'title' => 'Planned downtime',
            'content' => 'Deploying updates tonight.',
            'level' => Notice::LEVEL_WARNING,
            'location' => Notice::LOCATION_BELOW_HEADER,
            'audience_scope' => Notice::AUDIENCE_ALL_USERS,
            'show_on_all_pages' => false,
            'show_on_routes' => ['sessions.index'],
            'dismissable' => true,
            'enabled' => true,
        ]);

    expect($createResponse->status())->toBe(201);
    expect($createResponse->json('notice.title'))->toBe('Planned downtime');

    $noticeId = (int) $createResponse->json('notice.id');

    $this->assertDatabaseHas('notices', [
        'id' => $noticeId,
        'title' => 'Planned downtime',
        'enabled' => 1,
    ]);

    $updateResponse = $this->actingAs($admin)
        ->patchJson(route('admin.notices.update', $noticeId), [
            'title' => 'Maintenance complete',
            'content' => 'All services restored.',
            'level' => Notice::LEVEL_INFO,
            'location' => Notice::LOCATION_BELOW_NAV,
            'audience_scope' => Notice::AUDIENCE_ADMINS_ONLY,
            'show_on_all_pages' => true,
            'show_on_routes' => [],
            'dismissable' => false,
            'enabled' => true,
        ]);

    expect($updateResponse->status())->toBe(200);
    expect($updateResponse->json('notice.title'))->toBe('Maintenance complete');
    expect($updateResponse->json('notice.show_on_all_pages'))->toBeTrue();
    expect($updateResponse->json('notice.dismissable'))->toBeFalse();
    expect($updateResponse->json('notice.audience_scope'))->toBe(Notice::AUDIENCE_ADMINS_ONLY);

    $deleteResponse = $this->actingAs($admin)
        ->deleteJson(route('admin.notices.destroy', $noticeId));

    expect($deleteResponse->status())->toBe(200);

    $this->assertDatabaseMissing('notices', [
        'id' => $noticeId,
    ]);
});

test('notice route targeting only accepts full page routes', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.notices.store'), [
            'title' => 'Bad route target',
            'content' => 'Should fail.',
            'level' => Notice::LEVEL_INFO,
            'location' => Notice::LOCATION_ABOVE_NAV,
            'audience_scope' => Notice::AUDIENCE_ALL_USERS,
            'show_on_all_pages' => false,
            'show_on_routes' => ['notifications.index'],
            'dismissable' => true,
            'enabled' => true,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['show_on_routes.0']);
});

test('admin can reorder notices within the same location only', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $first = Notice::query()->create([
        'title' => 'First',
        'content' => 'First notice',
        'level' => Notice::LEVEL_INFO,
        'location' => Notice::LOCATION_ABOVE_NAV,
        'position' => 1,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    $second = Notice::query()->create([
        'title' => 'Second',
        'content' => 'Second notice',
        'level' => Notice::LEVEL_WARNING,
        'location' => Notice::LOCATION_ABOVE_NAV,
        'position' => 2,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    $otherLocation = Notice::query()->create([
        'title' => 'Other location',
        'content' => 'Other location notice',
        'level' => Notice::LEVEL_INFO,
        'location' => Notice::LOCATION_BELOW_NAV,
        'position' => 1,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.notices.reorder'), [
            'location' => Notice::LOCATION_ABOVE_NAV,
            'notice_ids' => [$second->id, $first->id],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notice order updated.');

    expect($second->refresh()->position)->toBe(1);
    expect($first->refresh()->position)->toBe(2);
    expect($otherLocation->refresh()->position)->toBe(1);

    $this->actingAs($admin)
        ->patchJson(route('admin.notices.reorder'), [
            'location' => Notice::LOCATION_ABOVE_NAV,
            'notice_ids' => [$second->id, $otherLocation->id],
        ])
        ->assertStatus(422);
});

test('admin can render markdown preview for notices', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.notices.preview'), [
            'content' => "**Heads up**\n\n- Bring charts",
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('content_html', "<p><strong>Heads up</strong></p>\n<ul>\n<li>Bring charts</li>\n</ul>\n");
});

test('non admin cannot access notice markdown preview endpoint', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->postJson(route('admin.notices.preview'), [
            'content' => 'No access',
        ])
        ->assertForbidden();
});

test('admin can clear notice dismissals for all users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();

    $notice = Notice::query()->create([
        'title' => 'Session reminder',
        'content' => 'Bring your charts.',
        'level' => Notice::LEVEL_INFO,
        'location' => Notice::LOCATION_BELOW_HEADER,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    NoticeDismissal::query()->create([
        'notice_id' => $notice->id,
        'user_id' => $userOne->id,
        'dismissed_at' => now(),
    ]);

    NoticeDismissal::query()->create([
        'notice_id' => $notice->id,
        'user_id' => $userTwo->id,
        'dismissed_at' => now(),
    ]);

    $this->assertDatabaseCount('notice_dismissals', 2);

    $this->actingAs($admin)
        ->deleteJson(route('admin.notices.dismissals.clear', $notice))
        ->assertOk()
        ->assertJsonPath('message', 'Dismissals cleared. This notice can show again.');

    $this->assertDatabaseCount('notice_dismissals', 0);
});

test('non admin cannot clear notice dismissals', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $notice = Notice::query()->create([
        'title' => 'Session reminder',
        'content' => 'Bring your charts.',
        'level' => Notice::LEVEL_INFO,
        'location' => Notice::LOCATION_BELOW_HEADER,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('admin.notices.dismissals.clear', $notice))
        ->assertForbidden();
});

test('enabled notices render on full pages only and dismissals hide them for that user', function () {
    $user = User::factory()->create();

    $notice = Notice::query()->create([
        'title' => 'Session reminder',
        'content' => 'Bring your charts.',
        'level' => Notice::LEVEL_INFO,
        'location' => Notice::LOCATION_BELOW_HEADER,
        'audience_scope' => Notice::AUDIENCE_ALL_USERS,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => true,
        'enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('Bring your charts.');

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonMissing(['title' => 'Session reminder']);

    $session = JamSession::query()->create([
        'name' => 'Partial Test Session',
        'date' => now()->addWeek()->toDateString(),
    ]);

    Set::query()->create([
        'name' => 'Partial Test Set',
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertDontSee('Bring your charts.');

    $this->actingAs($user)
        ->postJson(route('notices.dismiss', $notice))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertDontSee('Bring your charts.');
});

test('admin only notices are hidden from non admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    Notice::query()->create([
        'title' => 'Admins notice',
        'content' => 'Admin eyes only.',
        'level' => Notice::LEVEL_WARNING,
        'location' => Notice::LOCATION_ABOVE_NAV,
        'audience_scope' => Notice::AUDIENCE_ADMINS_ONLY,
        'show_on_all_pages' => true,
        'show_on_routes' => [],
        'dismissable' => false,
        'enabled' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('Admin eyes only.');

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertDontSee('Admin eyes only.');
});
