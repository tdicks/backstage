<?php

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamStandardSong;
use App\Models\Set;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
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
    expect($requester->notifications()->latest()->first()?->data['type_key'])->toBe(NotificationTypeCatalog::SONG_REQUEST_ACCEPTED);
    expect($requester->notifications()->latest()->first()?->data['body'])->toContain('Just Like Heaven');
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

test('approving a song request applies requested slot capabilities to requester profile', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Capabilities Jam',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Capabilities Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $this->actingAs($requester)
        ->post(route('song-requests.store', $set), [
            'artist' => 'Metallica',
            'title' => 'Enter Sandman',
            'slot_names' => ['vocals', 'lead_guitar'],
        ])
        ->assertRedirect();

    $songRequest = SongRequest::query()->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('song-requests.respond', $songRequest), [
            'status' => 'accepted',
        ])
        ->assertRedirect();

    $song = Song::query()->whereKey($songRequest->refresh()->song_id)->firstOrFail();
    $requestedSlots = $song->slots()->whereIn('name', ['vocals', 'lead_guitar'])->get();

    expect($requestedSlots)->toHaveCount(2)
        ->and($requestedSlots->pluck('user_id')->filter()->all())->toBe([$requester->id, $requester->id])
        ->and($requester->fresh()->slotCoverageState('vocals'))->toBe(User::SLOT_COVERAGE_CAN)
        ->and($requester->fresh()->slotCoverageState('lead_guitar'))->toBe(User::SLOT_COVERAGE_CAN);
});

test('a requester can remove their own pending song request via ajax', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $otherUser = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Requester Remove Jam',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Requester Remove Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $songRequest = SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Removal Band',
        'title' => 'Removal Song',
        'notes' => 'Please remove me',
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($requester)
        ->patchJson(route('song-requests.respond', $songRequest), [
            'status' => SongRequest::STATUS_REJECTED,
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Song request updated.',
        ]);

    expect($songRequest->refresh()->status)->toBe(SongRequest::STATUS_REJECTED);
    expect($songRequest->responded_by_user_id)->toBe($requester->id);

    $otherRequest = SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Blocked Band',
        'title' => 'Blocked Song',
        'notes' => null,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($otherUser)
        ->patchJson(route('song-requests.respond', $otherRequest), [
            'status' => SongRequest::STATUS_REJECTED,
        ])
        ->assertForbidden();
});

test('set cards show requester as you for the current user', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create(['name' => 'Requester Name']);

    $session = JamSession::create([
        'name' => 'Requester Label Jam',
        'date' => now()->addDays(6),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Requester Label Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $songRequest = SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Label Band',
        'title' => 'Label Song',
        'notes' => null,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($requester)
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Requested by you')
        ->assertSee('x-show="songRequestsPendingCount > 0"', false)
        ->assertSee('data-song-request-id="'.$songRequest->id.'"', false);
});

test('set cards render ajax song request approval controls for set owners', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Approval Controls Jam',
        'date' => now()->addDays(6),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Approval Controls Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $template = BandTemplate::create(['name' => 'Owner Approval Template']);
    $template->slots()->create(['name' => 'vocals']);

    SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Owner Band',
        'title' => 'Owner Song',
        'notes' => null,
        'band_template_id' => $template->id,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('x-model="bandTemplateId"', false)
        ->assertSee("@click=\"respond('accepted')\"", false)
        ->assertSee("@click=\"respond('rejected')\"", false)
        ->assertSee('decrementApprovalsCounter', false);
});

test('a non-owner can request a song via ajax and receive json success', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Ajax Song Request Jam',
        'date' => now()->addDays(7),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Ajax Song Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $this->actingAs($requester)
        ->postJson(route('song-requests.store', $set), [
            'artist' => 'Ajax Artist',
            'title' => 'Ajax Song',
            'notes' => 'Ajax note',
        ])
        ->assertCreated()
        ->assertJson([
            'message' => 'Song request submitted to the set owner.',
        ]);

    expect(SongRequest::query()->where('set_id', $set->id)->where('title', 'Ajax Song')->exists())->toBeTrue();

    $this->actingAs($requester)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('@submit.prevent="submitSongRequest($event)"', false)
        ->assertSee('songRequestStoreUrl', false);
});

test('request a song modal selects the title from Deezer suggestion objects', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Autocomplete Request Jam',
        'date' => now()->addDays(7),
        'description' => null,
    ]);
    $set = Set::create([
        'name' => 'Autocomplete Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $this->actingAs($requester)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee(':key="`request-title-${track.title}`"', false)
        ->assertSee('@click="selectRequestTitleSuggestion(track.title)"', false)
        ->assertSee('x-text="track.title"', false);
});

test('request a song modal shows catalog request controls in set shell', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Catalog Modal Jam',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Catalog Modal Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    JamStandardSong::query()->create([
        'artist' => 'Pearl Jam',
        'title' => 'Alive',
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('x-model="requestSongMode"', false)
        ->assertSee('x-model="requestCatalogSongId"', false)
        ->assertSee('name="jam_standard_song_id"', false)
        ->assertSee('name="slot_names[]"', false);
});

test('a non-owner can request a catalog song with slot capabilities', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Catalog Request Jam',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Catalog Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'performed' => false,
        'song_requests' => true,
    ]);

    $catalogSong = JamStandardSong::query()->create([
        'artist' => 'Muse',
        'title' => 'Hysteria',
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->post(route('song-requests.store', $set), [
            'artist' => 'Muse',
            'title' => 'Hysteria',
            'jam_standard_song_id' => $catalogSong->id,
            'slot_names' => ['bass'],
            'notes' => 'From catalog please',
        ])
        ->assertRedirect();

    $songRequest = SongRequest::query()->firstOrFail();

    expect($songRequest->jam_standard_song_id)->toBe($catalogSong->id)
        ->and($songRequest->requested_slot_names)->toBe(['bass']);
});
