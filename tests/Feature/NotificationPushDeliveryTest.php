<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('push delivery is attempted when push preferences are enabled', function () {
    $actor = User::factory()->create();
    $recipient = User::factory()->create([
        'notification_preferences' => [
            NotificationTypeCatalog::SET_UPDATED => [
                'enabled' => true,
                'popup' => true,
                'email' => true,
                'push' => true,
                'text' => false,
            ],
        ],
    ]);

    Setting::query()->updateOrCreate(
        ['key' => NotificationTypeCatalog::adminSettingKey(NotificationTypeCatalog::SET_UPDATED, 'push')],
        ['name' => 'Set updated Push', 'input_type' => 'checkbox', 'value' => '1']
    );

    $mock = Mockery::mock(WebPushService::class);
    $mock->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn (User $user, array $content) => $user->is($recipient) && ($content['type_key'] ?? null) === NotificationTypeCatalog::SET_UPDATED)
        ->andReturnNull();

    app()->instance(WebPushService::class, $mock);

    app(NotificationService::class)->notifyUsers(
        NotificationTypeCatalog::SET_UPDATED,
        [$recipient],
        $actor,
        [
            'title' => 'Set updated',
            'body' => 'A set was updated.',
            'action_url' => '/sessions/1',
            'action_label' => 'Open set',
        ],
        false
    );
});

test('push delivery is skipped when push preferences are disabled', function () {
    $actor = User::factory()->create();
    $recipient = User::factory()->create([
        'notification_preferences' => [
            NotificationTypeCatalog::SET_UPDATED => [
                'enabled' => true,
                'popup' => true,
                'email' => true,
                'push' => false,
                'text' => false,
            ],
        ],
    ]);

    Setting::query()->updateOrCreate(
        ['key' => NotificationTypeCatalog::adminSettingKey(NotificationTypeCatalog::SET_UPDATED, 'push')],
        ['name' => 'Set updated Push', 'input_type' => 'checkbox', 'value' => '1']
    );

    $mock = Mockery::mock(WebPushService::class);
    $mock->shouldNotReceive('sendToUser');

    app()->instance(WebPushService::class, $mock);

    app(NotificationService::class)->notifyUsers(
        NotificationTypeCatalog::SET_UPDATED,
        [$recipient],
        $actor,
        [
            'title' => 'Set updated',
            'body' => 'A set was updated.',
            'action_url' => '/sessions/1',
            'action_label' => 'Open set',
        ],
        false
    );
});
