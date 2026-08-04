<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can mark a tour as completed', function () {
    $user = User::factory()->create([
        'feature_tour_state' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('feature-tours.state.update'), [
            'once_key' => 'onboarding-v1',
            'action' => 'complete',
        ])
        ->assertOk()
        ->assertJsonPath('state.completed.onboarding-v1', true)
        ->assertJsonMissingPath('state.prompt_dismissed.onboarding-v1')
        ->assertJsonMissingPath('state.opted_out.onboarding-v1');

    $user->refresh();

    expect($user->feature_tour_state['completed']['onboarding-v1'] ?? false)->toBeTrue()
        ->and(isset($user->feature_tour_state['prompt_dismissed']['onboarding-v1']))->toBeFalse();
});

test('authenticated users can dismiss and clear prompt dismissal', function () {
    $user = User::factory()->create([
        'feature_tour_state' => [
            'completed' => [],
            'prompt_dismissed' => [],
            'opted_out' => [],
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('feature-tours.state.update'), [
            'once_key' => 'onboarding-v1',
            'action' => 'dismiss_prompt',
        ])
        ->assertOk()
        ->assertJsonPath('state.prompt_dismissed.onboarding-v1', true);

    $this->actingAs($user)
        ->postJson(route('feature-tours.state.update'), [
            'once_key' => 'onboarding-v1',
            'action' => 'clear_prompt_dismissal',
        ])
        ->assertOk()
        ->assertJsonMissingPath('state.prompt_dismissed.onboarding-v1');

    $user->refresh();

    expect(isset($user->feature_tour_state['prompt_dismissed']['onboarding-v1']))->toBeFalse();
});

test('authenticated users can opt out and clear opt out state', function () {
    $user = User::factory()->create([
        'feature_tour_state' => [
            'completed' => [],
            'prompt_dismissed' => [],
            'opted_out' => [],
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('feature-tours.state.update'), [
            'once_key' => 'onboarding-v1',
            'action' => 'opt_out',
        ])
        ->assertOk()
        ->assertJsonPath('state.opted_out.onboarding-v1', true)
        ->assertJsonMissingPath('state.prompt_dismissed.onboarding-v1');

    $this->actingAs($user)
        ->postJson(route('feature-tours.state.update'), [
            'once_key' => 'onboarding-v1',
            'action' => 'clear_opt_out',
        ])
        ->assertOk()
        ->assertJsonMissingPath('state.opted_out.onboarding-v1');

    $user->refresh();

    expect(isset($user->feature_tour_state['opted_out']['onboarding-v1']))->toBeFalse();
});

test('feature tour state update route requires authentication', function () {
    $this->postJson(route('feature-tours.state.update'), [
        'once_key' => 'onboarding-v1',
        'action' => 'complete',
    ])->assertUnauthorized();
});
