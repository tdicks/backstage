<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('an authenticated HTML page visit updates last seen at most once every fifteen minutes', function () {
    $user = User::factory()->create();
    $cacheKey = "user:last-seen:{$user->id}";
    Cache::forget($cacheKey);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $initialLastSeenAt = $user->refresh()->last_seen_at;

    expect($initialLastSeenAt)->not->toBeNull();

    $this->travel(1)->minutes();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->refresh()->last_seen_at->equalTo($initialLastSeenAt))->toBeTrue();

    $this->travel(15)->minutes();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->refresh()->last_seen_at->greaterThan($initialLastSeenAt))->toBeTrue();
});

test('JSON requests do not update last seen', function () {
    $user = User::factory()->create();
    Cache::forget("user:last-seen:{$user->id}");

    $this->actingAs($user)->getJson(route('dashboard'))->assertOk();

    expect($user->refresh()->last_seen_at)->toBeNull();
});
