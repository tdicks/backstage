<?php

use App\Models\BandTemplate;
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
            'notes' => 'Bring a sustain pedal.',
            'user_id' => null,
        ])
        ->assertRedirect();

    expect(Slot::query()
        ->where('song_id', $song->id)
        ->where('name', 'keys')
        ->where('notes', 'Bring a sustain pedal.')
        ->exists())
        ->toBeTrue();
});

test('set owner can apply a band template without duplicating existing slot types', function () {
    $owner = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Template Slot Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);
    $set = Set::create([
        'name' => 'Template Slot Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);
    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'The Band',
        'title' => 'The Song',
        'notes' => null,
        'position' => 1,
    ]);
    $template = BandTemplate::create(['name' => 'Rock Band']);
    $template->slots()->createMany([
        ['name' => 'vocals'],
        ['name' => 'bass'],
        ['name' => 'drums'],
    ]);
    Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->postJson(route('slots.store', $song), [
            'addition_mode' => 'template',
            'band_template_id' => $template->id,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Band template applied.')
        ->assertJsonCount(2, 'html')
        ->assertJsonPath('html.0', fn (string $html) => str($html)->contains('data-slot-id'));

    expect($song->slots()->orderBy('position')->pluck('name')->all())
        ->toBe(['vocals', 'bass', 'drums']);
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

test('set owner can update a slot note', function () {
    $owner = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Notes Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Notes Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Radiohead',
        'title' => 'Karma Police',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('slots.update', $slot), [
            'name' => 'vocals',
            'notes' => 'Cue the harmony on the chorus.',
        ])
        ->assertOk()
        ->assertJsonPath('slot.notes', 'Cue the harmony on the chorus.');

    expect($slot->refresh()->notes)->toBe('Cue the harmony on the chorus.');
});
