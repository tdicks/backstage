<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('authenticated layout title includes pending notifications and approvals total', function () {
    $user = User::factory()->create();
    $requester = User::factory()->create();

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AppActivityNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'type_key' => 'test.notification',
            'title' => 'Test',
            'body' => 'Test notification',
            'action_url' => null,
            'action_label' => 'Open',
            'popup' => false,
        ], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'dismissed_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = JamSession::query()->create([
        'name' => 'Title Count Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Title Count Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    SongRequest::query()->create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Title Artist',
        'title' => 'Title Song',
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<title>(2) Dashboard | Backstage</title>', false)
        ->assertSee('name="backstage-app-name" content="Backstage"', false)
        ->assertSee('name="backstage-page-name" content="Dashboard"', false)
        ->assertSee('name="backstage-unread-count" content="1"', false)
        ->assertSee('name="backstage-approval-count" content="1"', false)
        ->assertSee('name="backstage-pending-total" content="2"', false)
        ->assertSee('name="backstage-authenticated" content="1"', false);
});

test('authenticated layout title omits prefix when no pending items', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<title>Dashboard | Backstage</title>', false)
        ->assertSee('name="backstage-pending-total" content="0"', false);
});

test('guest layout title is page aware without notification prefix', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<title>Login | Backstage</title>', false)
        ->assertSee('name="backstage-app-name" content="Backstage"', false)
        ->assertSee('name="backstage-authenticated" content="0"', false);
});

test('frontend title sync reacts to seen notifications and approval processing events', function () {
    $appScript = file_get_contents(resource_path('js/app.js'));
    $notificationsStore = file_get_contents(resource_path('js/stores/notifications.js'));

    expect($notificationsStore)
        ->toContain("window.dispatchEvent(new CustomEvent('notifications-updated'))");

    expect($appScript)
        ->toContain("window.addEventListener('notifications-updated'")
        ->toContain("window.addEventListener('approvals-count-changed'")
        ->toContain("window.addEventListener('approvals-count-refreshed'")
        ->toContain("window.addEventListener('target-consent-processed'")
        ->toContain("window.addEventListener('pending-approval-processed'")
        ->toContain('const appName = document.querySelector(\'meta[name="backstage-app-name"]\')?.content?.trim() || \'Backstage\';')
        ->toContain('sameName ? `${prefix}${appName}` : `${prefix}${pageName} | ${appName}`');
});
