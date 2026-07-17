<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can assign a manual performer name to a slot', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Manual Performer Session',
        'date' => now()->addDay(),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Manual Performer Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'artist' => 'Pearl Jam',
        'title' => 'Alive',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => null,
    ]);

    $this->actingAs($owner)
        ->patch(route('slots.update', $slot), [
            'name' => 'vocals',
            'user_id' => null,
            'guest_name' => 'Chris D',
        ])
        ->assertRedirect();

    expect($slot->refresh()->guest_name)->toBe('Chris D');
    expect($slot->user_id)->toBeNull();
    expect($slot->isOpen())->toBeFalse();
});

test('admin can assign a manual performer name to another users slot', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Admin Manual Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Admin Manual Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'artist' => 'Foo Fighters',
        'title' => 'Everlong',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'user_id' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('slots.update', $slot), [
            'name' => 'drums',
            'user_id' => null,
            'guest_name' => 'Pat K',
        ])
        ->assertRedirect();

    expect($slot->refresh()->guest_name)->toBe('Pat K');
});

test('slot update rejects assigning both registered user and manual performer name', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Validation Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Validation Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'artist' => 'The Killers',
        'title' => 'Mr. Brightside',
        'notes' => null,
        'set_id' => $set->id,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'user_id' => null,
    ]);

    $this->actingAs($owner)
        ->from(route('sessions.show', $session))
        ->patch(route('slots.update', $slot), [
            'name' => 'bass',
            'user_id' => $assignee->id,
            'guest_name' => 'Guest Name',
        ])
        ->assertRedirect(route('sessions.show', $session))
        ->assertSessionHasErrors('guest_name');

    expect($slot->refresh()->user_id)->toBeNull();
    expect($slot->guest_name)->toBeNull();
});

test('users cannot request slots that are assigned to manual performers', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Assigned Session',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Assigned Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Muse',
        'title' => 'Starlight',
        'notes' => null,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'lead_guitar',
        'user_id' => null,
        'guest_name' => 'Alex P',
    ]);

    $this->actingAs($requester)
        ->post(route('slot-assignments.request', $slot))
        ->assertRedirect();

    expect(SlotAssignment::query()->count())->toBe(0);
});
