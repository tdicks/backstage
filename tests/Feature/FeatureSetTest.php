<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('feature sets are shown before regular sets on session page', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Feature Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    Set::create([
        'name' => 'Regular Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'feature_set' => false,
        'performed' => false,
        'signups_open' => true,
    ]);

    Set::create([
        'name' => 'Featured Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'feature_set' => true,
        'performed' => false,
        'signups_open' => true,
    ]);

    $orderedNames = $session->fresh()->sets->pluck('name')->all();

    expect($orderedNames)->toBe(['Featured Set', 'Regular Set']);
});
