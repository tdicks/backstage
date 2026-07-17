<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can close and reopen signups', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Toggle Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Toggle Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.close-signups', $set))
        ->assertRedirect();

    expect($set->refresh()->signups_open)->toBeFalse();

    $this->actingAs($owner)
        ->patch(route('sets.open-signups', $set))
        ->assertRedirect();

    expect($set->refresh()->signups_open)->toBeTrue();
});

test('non owner cannot close signups', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Auth Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Auth Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($other)
        ->patch(route('sets.close-signups', $set))
        ->assertForbidden();

    expect($set->refresh()->signups_open)->toBeTrue();
});

test('closed signups block new slot requests', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Closed Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Closed Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => false,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Rush',
        'title' => 'Tom Sawyer',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($requester)
        ->post(route('slot-assignments.request', $slot))
        ->assertRedirect();

    expect(SlotAssignment::query()->count())->toBe(0);
});

test('admin can change set owner from set update endpoint', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Change Session',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Change Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('sets.update', $set), [
            'name' => 'Owner Change Set',
            'description' => null,
            'performed' => 0,
            'owner_id' => $newOwner->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->owner_id)->toBe($newOwner->id);
});

test('non-admin cannot change set owner via set update endpoint', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Guard Session',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Guard Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Owner Guard Set',
            'description' => null,
            'performed' => 0,
            'owner_id' => $other->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->owner_id)->toBe($owner->id);
});

test('set owner can change which jam session the set belongs to', function () {
    $owner = User::factory()->create();

    $originalSession = JamSession::create([
        'name' => 'Original Session',
        'date' => now()->addDays(6),
        'description' => null,
    ]);

    $targetSession = JamSession::create([
        'name' => 'Target Session',
        'date' => now()->addDays(7),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Movable Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $originalSession->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Movable Set',
            'description' => null,
            'performed' => 0,
            'jam_session_id' => $targetSession->id,
        ])
        ->assertRedirect();

    expect($set->refresh()->jam_session_id)->toBe($targetSession->id);
});

test('non-admin cannot update feature set flag', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Feature Guard Session',
        'date' => now()->addDays(8),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Feature Guard Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'feature_set' => false,
        'performed' => false,
        'signups_open' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('sets.update', $set), [
            'name' => 'Feature Guard Set',
            'description' => null,
            'performed' => 0,
            'feature_set' => 1,
        ])
        ->assertRedirect();

    expect($set->refresh()->feature_set)->toBeFalse();
});