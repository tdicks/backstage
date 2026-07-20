<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set owner can add a keys slot to a song', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Keys Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Keys Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Doors',
        'title' => 'Light My Fire',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->post(route('slots.store', $song), [
            'name' => 'keys',
            'user_id' => null,
        ])
        ->assertRedirect();

    expect(Slot::query()
        ->where('song_id', $song->id)
        ->where('name', 'keys')
        ->exists())
        ->toBeTrue();
});

test('user receives notification when manually assigned to a slot', function () {
    $owner = User::factory()->create();
    $performer = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Manual Assignment Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Manual Assignment Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Fleetwood Mac',
        'title' => 'Dreams',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    // Manually assign the performer to the slot
    $this->actingAs($owner)
        ->patch(route('slots.update', $slot), [
            'name' => 'vocals',
            'user_id' => $performer->id,
        ])
        ->assertRedirect();

    // Verify the performer has a notification
    expect($performer->notifications()->where('type', 'App\\Notifications\\AppActivityNotification')->first()?->data['type_key'])
        ->toBe(NotificationTypeCatalog::SLOT_MANUALLY_ASSIGNED);

    expect($performer->notifications()->first()?->data['title'])
        ->toContain('You\'ve been assigned to a slot');
});