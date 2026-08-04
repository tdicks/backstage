<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('reset command clears a specific once_key', function () {
    $user = User::factory()->create([
        'feature_tour_state' => [
            'completed' => ['onboarding-v1' => true, 'other-v1' => true],
            'prompt_dismissed' => ['onboarding-v1' => true],
            'opted_out' => ['onboarding-v1' => true],
        ],
    ]);

    $exitCode = Artisan::call('app:reset-feature-tour-state', [
        'user' => (string) $user->id,
        'once_key' => 'onboarding-v1',
    ]);

    expect($exitCode)->toBe(0);

    $user->refresh();

    expect(isset($user->feature_tour_state['completed']['onboarding-v1']))->toBeFalse()
        ->and(isset($user->feature_tour_state['prompt_dismissed']['onboarding-v1']))->toBeFalse()
        ->and(isset($user->feature_tour_state['opted_out']['onboarding-v1']))->toBeFalse()
        ->and($user->feature_tour_state['completed']['other-v1'] ?? false)->toBeTrue();
});

test('reset command can clear all feature tour state', function () {
    $user = User::factory()->create([
        'feature_tour_state' => [
            'completed' => ['onboarding-v1' => true],
            'prompt_dismissed' => ['onboarding-v1' => true],
            'opted_out' => ['onboarding-v1' => true],
        ],
    ]);

    $exitCode = Artisan::call('app:reset-feature-tour-state', [
        'user' => $user->email,
        '--all' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect($user->refresh()->feature_tour_state)->toBeNull();
});

test('reset command requires once_key when --all is not used', function () {
    $user = User::factory()->create();

    $exitCode = Artisan::call('app:reset-feature-tour-state', [
        'user' => (string) $user->id,
    ]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('Provide once_key or use --all.');
});
