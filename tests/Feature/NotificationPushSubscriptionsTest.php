<?php

use App\Models\NotificationPushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can store a push subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.test/subscriptions/abc123',
            'keys' => [
                'p256dh' => 'public-key-value',
                'auth' => 'auth-token-value',
            ],
            'content_encoding' => 'aesgcm',
        ])
        ->assertCreated();

    $subscription = NotificationPushSubscription::query()->firstOrFail();

    expect($subscription->user_id)->toBe($user->id)
        ->and($subscription->endpoint)->toBe('https://push.example.test/subscriptions/abc123')
        ->and($subscription->public_key)->toBe('public-key-value')
        ->and($subscription->auth_token)->toBe('auth-token-value')
        ->and($subscription->content_encoding)->toBe('aesgcm')
        ->and($subscription->last_seen_at)->not->toBeNull();
});

test('storing the same endpoint updates existing push subscription', function () {
    $user = User::factory()->create();

    NotificationPushSubscription::query()->create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example.test/subscriptions/abc123',
        'endpoint_hash' => hash('sha256', 'https://push.example.test/subscriptions/abc123'),
        'public_key' => 'old-public-key',
        'auth_token' => 'old-auth-token',
        'content_encoding' => null,
        'user_agent' => 'Old Agent',
    ]);

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.test/subscriptions/abc123',
            'keys' => [
                'p256dh' => 'new-public-key',
                'auth' => 'new-auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ])
        ->assertOk();

    expect(NotificationPushSubscription::query()->count())->toBe(1);

    $subscription = NotificationPushSubscription::query()->firstOrFail();
    expect($subscription->public_key)->toBe('new-public-key')
        ->and($subscription->auth_token)->toBe('new-auth-token')
        ->and($subscription->content_encoding)->toBe('aes128gcm');
});

test('authenticated user can remove only their own push subscription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $endpoint = 'https://push.example.test/subscriptions/abc123';

    NotificationPushSubscription::query()->create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'endpoint_hash' => hash('sha256', $endpoint),
        'public_key' => 'public-key',
        'auth_token' => 'auth-token',
    ]);

    NotificationPushSubscription::query()->create([
        'user_id' => $otherUser->id,
        'endpoint' => $endpoint,
        'endpoint_hash' => hash('sha256', $endpoint),
        'public_key' => 'other-public-key',
        'auth_token' => 'other-auth-token',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('notifications.push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertOk();

    expect(NotificationPushSubscription::query()->where('user_id', $user->id)->exists())->toBeFalse();
    expect(NotificationPushSubscription::query()->where('user_id', $otherUser->id)->exists())->toBeTrue();
});
