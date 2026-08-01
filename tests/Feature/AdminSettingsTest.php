<?php

use App\Mail\AdminTestEmail;
use App\Models\NotificationPushSubscription;
use App\Models\Setting;
use App\Models\SlotType;
use App\Models\User;
use App\Services\WebPushService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('settings seeder creates the social login setting', function () {
    $this->seed(SettingsSeeder::class);

    $this->assertDatabaseHas('settings', [
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '0',
    ]);
});

test('admin can view settings page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Setting::query()->create([
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '1',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Application Settings')
        ->assertSee('Notifications')
        ->assertSee('Slot Types')
        ->assertSee('Slot request accepted')
        ->assertSee('Slot recommendation accepted')
        ->assertSee('New user registered')
        ->assertSee('Admin')
        ->assertSee('Send Test Push Notification')
        ->assertSee('Send Test Email Notification')
        ->assertSee('Apply to all')
        ->assertSee('Enable Social Logins')
        ->assertSee('enable_social_logins');
});

test('non admin cannot access settings page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.settings.index'))
        ->assertForbidden();
});

test('admin can update settings without a page refresh', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $setting = Setting::query()->create([
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '1',
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.settings.update', $setting), [
            'value' => false,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Enable Social Logins updated.')
        ->assertJsonPath('setting.key', 'enable_social_logins')
        ->assertJsonPath('setting.value', '0');

    $this->assertDatabaseHas('settings', [
        'id' => $setting->id,
        'value' => '0',
    ]);
});

test('non admin cannot update settings', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $setting = Setting::query()->create([
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '1',
    ]);

    $this->actingAs($user)
        ->patchJson(route('admin.settings.update', $setting), [
            'value' => false,
        ])
        ->assertForbidden();
});

test('admin can add and update slot types from settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->postJson(route('admin.slot-types.store'), ['name' => 'Trumpet'])
        ->assertCreated()
        ->assertJsonPath('slot_type.key', 'trumpet')
        ->assertJsonPath('slot_type.name', 'Trumpet');

    $slotType = SlotType::query()->where('key', 'trumpet')->firstOrFail();

    $this->actingAs($admin)
        ->patchJson(route('admin.slot-types.update', $slotType), [
            'name' => 'Brass',
            'sort_order' => 15,
            'active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('slot_type.name', 'Brass')
        ->assertJsonPath('slot_type.active', false);

    expect($slotType->refresh()->key)->toBe('trumpet')
        ->and($slotType->active)->toBeFalse();
});

test('admin can trigger a test push notification from settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    NotificationPushSubscription::query()->create([
        'user_id' => $admin->id,
        'endpoint' => 'https://push.example.test/subscriptions/admin-test',
        'endpoint_hash' => hash('sha256', 'https://push.example.test/subscriptions/admin-test'),
        'public_key' => 'public-key',
        'auth_token' => 'auth-token',
    ]);

    $mock = Mockery::mock(WebPushService::class);
    $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
    $mock->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn (User $user, array $content) => $user->is($admin) && ($content['title'] ?? null) === 'Backstage push test')
        ->andReturnNull();

    app()->instance(WebPushService::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('admin.settings.push-test'))
        ->assertOk()
        ->assertJsonPath('message', 'Test push sent. If you do not receive it, check browser site permissions for notifications.');
});

test('non admin cannot trigger a test push notification from settings', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->postJson(route('admin.settings.push-test'))
        ->assertForbidden();
});

test('admin can trigger a test email from settings', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@example.test',
    ]);

    Mail::fake();

    $this->actingAs($admin)
        ->postJson(route('admin.settings.email-test'))
        ->assertOk()
        ->assertJsonPath('message', 'Test email sent to admin@example.test.');

    Mail::assertSent(AdminTestEmail::class, function (AdminTestEmail $mail) use ($admin): bool {
        return $mail->hasTo($admin->email)
            && $mail->recipient->is($admin);
    });
});

test('non admin cannot trigger a test email from settings', function () {
    $user = User::factory()->create(['is_admin' => false]);

    Mail::fake();

    $this->actingAs($user)
        ->postJson(route('admin.settings.email-test'))
        ->assertForbidden();

    Mail::assertNothingSent();
});
