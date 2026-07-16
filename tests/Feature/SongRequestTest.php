<?php

use App\Models\JamSession;
use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a non-owner can request a song and the owner can approve it', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Saturday Jam',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Main Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
    ]);

    $this->actingAs($requester)
        ->post(route('song-requests.store', $set), [
            'artist' => 'The Cure',
            'title' => 'Just Like Heaven',
            'notes' => 'Could fit near the end of the set.',
        ])
        ->assertRedirect();

    $songRequest = SongRequest::query()->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('song-requests.respond', $songRequest), [
            'status' => 'accepted',
        ])
        ->assertRedirect();

    expect($songRequest->refresh()->status)->toBe(SongRequest::STATUS_ACCEPTED);
    expect($songRequest->song_id)->not->toBeNull();
    expect(Song::query()->where('set_id', $set->id)->where('title', 'Just Like Heaven')->exists())->toBeTrue();
});

test('an owner can choose a band template when approving a song request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Template Jam',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $template = BandTemplate::create(['name' => 'Power Trio']);
    $template->slots()->create(['name' => 'vocals']);
    $template->slots()->create(['name' => 'bass']);
    $template->slots()->create(['name' => 'drums']);

    $set = Set::create([
        'name' => 'Feature Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
    ]);

    $this->actingAs($requester)
        ->post(route('song-requests.store', $set), [
            'artist' => 'The Beatles',
            'title' => 'Come Together',
            'notes' => null,
        ])
        ->assertRedirect();

    $songRequest = SongRequest::query()->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('song-requests.respond', $songRequest), [
            'status' => 'accepted',
            'band_template_id' => $template->id,
        ])
        ->assertRedirect();

    $song = Song::query()->where('set_id', $set->id)->where('title', 'Come Together')->firstOrFail();

    expect($song->slots()->pluck('name')->all())->toBe(['vocals', 'bass', 'drums']);
});