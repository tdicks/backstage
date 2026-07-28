<?php

use App\Models\User;
use App\Support\NotificationSettings;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification types are categorized correctly', function () {
    $definitions = NotificationTypeCatalog::definitions();

    expect($definitions['slot_request_accepted']['category'])->toBe('slots');
    expect($definitions['slot_request_received']['category'])->toBe('slots');
    expect($definitions['slot_recommendation_received']['category'])->toBe('slots');
    expect($definitions['slot_recommendation_accepted']['category'])->toBe('slots');
    expect($definitions['slot_dropped_from_set']['category'])->toBe('slots');
    expect($definitions['slot_manually_assigned']['category'])->toBe('slots');

    expect($definitions['set_collaborator_added']['category'])->toBe('sets');
    expect($definitions['set_collaborator_removed']['category'])->toBe('sets');
    expect($definitions['set_updated']['category'])->toBe('sets');
    expect($definitions['song_request_received']['category'])->toBe('sets');
    expect($definitions['song_request_accepted']['category'])->toBe('sets');

    $setTypes = collect(NotificationSettings::profileOptions(User::factory()->create())['sets']['options'])->pluck('type')->all();
    expect(array_search('song_request_received', $setTypes))->toBeLessThan(array_search('song_request_accepted', $setTypes));

    expect($definitions['jam_session_published']['category'])->toBe('jam_sessions');
    expect($definitions['jam_session_lock_changed']['category'])->toBe('jam_sessions');
    expect($definitions['jam_session_date_changed']['category'])->toBe('jam_sessions');

    expect($definitions['admin_user_registered']['category'])->toBe('admin');
    expect($definitions['admin_access_granted_to_user']['category'])->toBe('admin');
    expect($definitions['admin_access_revoked_from_user']['category'])->toBe('admin');
    expect($definitions['account_admin_access_granted']['category'])->toBe('admin');
    expect($definitions['account_admin_access_revoked']['category'])->toBe('admin');
    expect($definitions['account_admin_access_granted']['is_user_configurable'])->toBeFalse();
    expect($definitions['account_admin_access_revoked']['is_user_configurable'])->toBeFalse();
});

test('notification catalog has category definitions', function () {
    $categories = NotificationTypeCatalog::categories();

    expect($categories)->toHaveKeys(['slots', 'sets', 'jam_sessions', 'admin']);
    expect($categories['slots'])->toBe('Slots');
    expect($categories['sets'])->toBe('Sets');
    expect($categories['jam_sessions'])->toBe('Jam Sessions');
    expect($categories['admin'])->toBe('Admin');
});

test('profile options are grouped by category', function () {
    $user = User::factory()->create();
    $options = NotificationSettings::profileOptions($user);

    expect($options)->toHaveKeys(['slots', 'sets', 'jam_sessions']);
    expect($options)->not->toHaveKey('admin');

    // Verify each group has the correct structure
    foreach ($options as $group) {
        expect($group)->toHaveKeys(['category', 'label', 'options']);
        expect($group['options'])->not->toBeEmpty();

        // Verify each option has required fields
        foreach ($group['options'] as $option) {
            expect($option)->toHaveKeys(['type', 'label', 'description', 'enabled', 'popup', 'email', 'popup_available', 'email_available']);
        }
    }
});

test('admin profile options include the admin notification group', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $options = NotificationSettings::profileOptions($admin);

    expect($options)->toHaveKey('admin')
        ->and($options['admin']['label'])->toBe('Admin')
        ->and(collect($options['admin']['options'])->pluck('type')->all())
        ->toContain('admin_user_registered')
        ->toContain('admin_access_granted_to_user')
        ->toContain('admin_access_revoked_from_user')
        ->not->toContain('account_admin_access_granted')
        ->not->toContain('account_admin_access_revoked');
});

test('admin options are grouped by category', function () {
    $options = NotificationSettings::adminOptions();

    expect($options)->toHaveKeys(['slots', 'sets', 'jam_sessions', 'admin']);

    // Verify each group has the correct structure
    foreach ($options as $group) {
        expect($group)->toHaveKeys(['category', 'label', 'options']);
        expect($group['options'])->not->toBeEmpty();

        // Verify each option has required fields
        foreach ($group['options'] as $option) {
            expect($option)->toHaveKeys(['type', 'label', 'description', 'settings']);
        }
    }

    expect(collect($options['admin']['options'])->pluck('type')->all())
        ->toContain('admin_user_registered')
        ->toContain('admin_access_granted_to_user')
        ->toContain('admin_access_revoked_from_user')
        ->toContain('account_admin_access_granted')
        ->toContain('account_admin_access_revoked');
});
