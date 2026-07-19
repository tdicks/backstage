<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('settings seeder creates the social login setting', function () {
    $this->seed(SettingsSeeder::class);

    $this->assertDatabaseHas('settings', [
        'key' => 'enable_social_logins',
        'name' => 'Enable Social Logins',
        'input_type' => 'checkbox',
        'value' => '1',
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
