<?php

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\SlotType;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use App\Services\DeezerArtworkLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('planned sets page requires authentication', function () {
    $this->get(route('planned-sets.index'))
        ->assertRedirect(route('login'));
});

test('a user can create a planned set', function () {
    $user = User::factory()->create();
    $collaborator = User::factory()->create();
    $candidateSession = JamSession::query()->create([
        'name' => 'Candidate Jam',
        'date' => now()->addDays(9),
        'description' => null,
        'is_closed' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('planned-sets.store'), [
            'name' => 'Festival Warmup',
            'description' => 'Keep it tight and short.',
            'collaborator_ids' => [$collaborator->id],
            'is_hidden' => false,
            'free_for_all' => false,
            'song_requests' => true,
            'signups_open' => true,
            'candidate_session_ids' => [$candidateSession->id],
        ])
        ->assertCreated()
        ->assertJsonPath('set.name', 'Festival Warmup');

    $set = Set::query()->firstOrFail();

    expect($set->jam_session_id)->toBeNull()
        ->and($set->lifecycle_state)->toBe(Set::LIFECYCLE_DRAFT)
        ->and((bool) $set->song_requests)->toBeTrue()
        ->and((bool) $set->signups_open)->toBeTrue()
        ->and($set->candidate_session_ids)->toBe([$candidateSession->id])
        ->and($set->collaboratorUserIds())->toContain($collaborator->id);
});

test('a user can update planned set song requests and sign ups options', function () {
    $user = User::factory()->create();
    $candidateSession = JamSession::query()->create([
        'name' => 'Friday Option Jam',
        'date' => now()->addDays(6),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Options Draft',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'song_requests' => true,
        'signups_open' => true,
    ]);

    $this->actingAs($user)
        ->patchJson(route('planned-sets.update', $set), [
            'name' => 'Options Draft Updated',
            'description' => 'Updated options.',
            'is_hidden' => true,
            'free_for_all' => false,
            'song_requests' => false,
            'signups_open' => false,
            'candidate_session_ids' => [$candidateSession->id],
        ])
        ->assertOk()
        ->assertJsonPath('set.name', 'Options Draft Updated')
        ->assertJsonPath('set.song_requests', false)
        ->assertJsonPath('set.signups_open', false)
        ->assertJsonPath('set.candidate_session_ids.0', $candidateSession->id);

    $set->refresh();

    expect((bool) $set->song_requests)->toBeFalse()
        ->and((bool) $set->signups_open)->toBeFalse()
        ->and($set->candidate_session_ids)->toBe([$candidateSession->id]);
});

test('planned sets page derives availability from jam attendance', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $slotAssignee = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Late Night Set',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$voter->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $jamSession = JamSession::query()->create([
        'name' => 'Friday Jam',
        'date' => now()->addDays(7),
        'description' => null,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Metallica',
        'title' => 'Fade to Black',
        'position' => 1,
    ]);

    $song->slots()->create([
        'name' => 'guitar',
        'position' => 1,
        'user_id' => $slotAssignee->id,
    ]);

    $song->slots()->create([
        'name' => 'vocals',
        'position' => 2,
        'manual_performer_name' => 'Manual Performer',
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $owner->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $voter->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $slotAssignee->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $response = $this->actingAs($voter)
        ->get(route('planned-sets.index'))
        ->assertOk();

    $initialSets = collect($response->viewData('initialPlannedSets'));
    $availability = collect($initialSets->firstWhere('id', $set->id)['attendance_sessions'] ?? [])
        ->firstWhere('jam_session_id', $jamSession->id);

    expect($availability)->not->toBeNull()
        ->and($availability['my_status'])->toBe(JamSessionAttendance::STATUS_NOT_GOING)
        ->and($availability['counts']['going'])->toBe(2)
        ->and($availability['counts']['not_going'])->toBe(1)
        ->and($availability['counts']['maybe'])->toBe(0)
        ->and($availability['counts']['total'])->toBe(3)
        ->and($availability['display_counts']['going'])->toBe(3)
        ->and($availability['going_names'])->toContain('Manual Performer');
});

test('planned set slot badges show a manual assignment icon for manual performer names', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Manual Assignment Preview',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Example Artist',
        'title' => 'Example Song',
        'position' => 1,
    ]);

    $song->slots()->create([
        'name' => 'vocals',
        'position' => 1,
        'manual_performer_name' => 'Manual Performer',
    ]);

    $view = regressionResource('views/sets/planned/partials/set-card/songs-and-slots.blade.php');

    expectContainsAll($view, [
        'x-bind:title="slot.manual_performer_name ? \'Manually assigned\' : \'\'"',
        'x-heroicon-m-pencil-square',
        'slot.manual_performer_name',
    ]);
});

test('planned set slot popovers surface notes and manual assignment indicators', function () {
    $view = regressionResource('views/sets/planned/partials/set-card/songs-and-slots.blade.php');

    expectContainsAll($view, [
        'x-heroicon-m-chat-bubble-left-ellipsis',
        'slot.notes',
        'Notes',
        'x-bind:title="slot.manual_performer_name ? \'Manually assigned\' : \'\'"',
    ]);
});

test('planned set edit slot modal exposes a delete slot action', function () {
    $owner = User::factory()->create();

    Set::query()->create([
        'name' => 'Deleteable Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('planned-sets.index'))
        ->assertOk()
        ->assertSee('Delete Slot')
        ->assertSee('destroySlotUrlTemplate');
});

test('planned set edit song modal exposes a delete song action', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Song Delete Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $set->songs()->create([
        'artist' => 'Delete Artist',
        'title' => 'Delete Song',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('planned-sets.index'))
        ->assertOk()
        ->assertSee('Delete Song')
        ->assertSee('songDestroyUrlTemplate');
});

test('planned set song deletion works when the set has no jam session', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Planned Song Delete Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Delete Artist',
        'title' => 'Delete Song',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('songs.destroy', $song))
        ->assertOk()
        ->assertJsonPath('message', 'Song removed.');

    expect(Song::find($song->id))->toBeNull();
});

test('planned set song addition refreshes the cached artwork tile key', function () {
    $owner = User::factory()->create();
    $service = app(DeezerArtworkLookupService::class);

    $set = Set::query()->create([
        'name' => 'Artwork Refresh Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $set->songs()->create([
        'artist' => 'First Artist',
        'title' => 'First Song',
        'position' => 1,
    ]);

    $cacheKey = $service->artworkCacheKeyForSet($set);
    Cache::put($cacheKey, [['url' => 'https://example.test/old.jpg', 'label' => 'Old']]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.songs.store', $set), [
            'artist' => 'Second Artist',
            'title' => 'Second Song',
        ])
        ->assertCreated();

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('song deletion refreshes the cached artwork tile key for scheduled sets too', function () {
    $owner = User::factory()->create();
    $service = app(DeezerArtworkLookupService::class);

    $session = JamSession::query()->create([
        'name' => 'Artwork Refresh Session',
        'date' => now()->addDays(5),
        'description' => null,
    ]);

    $set = Set::query()->create([
        'name' => 'Artwork Refresh Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Delete Artist',
        'title' => 'Delete Song',
        'position' => 1,
    ]);

    $cacheKey = $service->artworkCacheKeyForSet($set);
    Cache::put($cacheKey, [['url' => 'https://example.test/old.jpg', 'label' => 'Old']]);

    $this->actingAs($owner)
        ->deleteJson(route('songs.destroy', $song))
        ->assertOk();

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('song updates refresh the cached artwork tile key for scheduled sets too', function () {
    $owner = User::factory()->create();
    $service = app(DeezerArtworkLookupService::class);

    $session = JamSession::query()->create([
        'name' => 'Artwork Update Session',
        'date' => now()->addDays(6),
        'description' => null,
    ]);

    $set = Set::query()->create([
        'name' => 'Artwork Update Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
        'position' => 1,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Old Artist',
        'title' => 'Old Song',
        'position' => 1,
    ]);

    $cacheKey = $service->artworkCacheKeyForSet($set);
    Cache::put($cacheKey, [['url' => 'https://example.test/old.jpg', 'label' => 'Old']]);

    $this->actingAs($owner)
        ->patchJson(route('songs.update', $song), [
            'artist' => 'New Artist',
            'title' => 'New Song',
        ])
        ->assertRedirect();

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('a planned set can be scheduled into a future open jam session', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Main Stage Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [],
    ]);

    $jamSession = JamSession::query()->create([
        'name' => 'Saturday Jam',
        'date' => now()->addDays(10),
        'description' => null,
        'is_closed' => false,
    ]);

    $set->update([
        'candidate_session_ids' => [$jamSession->id],
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $jamSession->id,
        ])
        ->assertOk()
        ->assertJsonPath('set_id', $set->id);

    expect($set->refresh()->jam_session_id)->toBe($jamSession->id)
        ->and($set->lifecycle_state)->toBe(Set::LIFECYCLE_SCHEDULED);
});

test('a planned set cannot be scheduled without candidate sessions', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'No Candidate Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [],
    ]);

    $jamSession = JamSession::query()->create([
        'name' => 'Publish Target Jam',
        'date' => now()->addDays(12),
        'description' => null,
        'is_closed' => false,
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $jamSession->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Add at least one candidate jam session before scheduling this set.');

    expect($set->refresh()->jam_session_id)->toBeNull();
});

test('a planned set can only be scheduled to one of its candidate sessions', function () {
    $owner = User::factory()->create();

    $candidateJam = JamSession::query()->create([
        'name' => 'Candidate Jam Session',
        'date' => now()->addDays(8),
        'description' => null,
        'is_closed' => false,
    ]);

    $otherJam = JamSession::query()->create([
        'name' => 'Other Jam Session',
        'date' => now()->addDays(9),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Candidate Restriction Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [$candidateJam->id],
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $otherJam->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Choose one of this set\'s candidate jam sessions.');

    expect($set->refresh()->jam_session_id)->toBeNull();
});

test('scheduling requires a not-going slot action when selected session has not-going participants', function () {
    $owner = User::factory()->create();
    $notGoingCollaborator = User::factory()->create();

    $jamSession = JamSession::query()->create([
        'name' => 'Not Going Action Jam',
        'date' => now()->addDays(11),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Not Going Action Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$notGoingCollaborator->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [$jamSession->id],
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $owner->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $notGoingCollaborator->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $jamSession->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Choose how to handle slots for participants marked Not Going.');

    expect($set->fresh()->jam_session_id)->toBeNull();
});

test('scheduling can clear slots assigned to not-going participants', function () {
    $owner = User::factory()->create();
    $notGoingPerformer = User::factory()->create();

    $jamSession = JamSession::query()->create([
        'name' => 'Release Not Going Jam',
        'date' => now()->addDays(13),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Release Not Going Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$notGoingPerformer->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [$jamSession->id],
    ]);

    $song = $set->songs()->create([
        'artist' => 'Daft Punk',
        'title' => 'Get Lucky',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'guitar',
        'position' => 1,
        'user_id' => $notGoingPerformer->id,
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $owner->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $notGoingPerformer->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $jamSession->id,
            'not_going_slot_action' => 'release_slots',
        ])
        ->assertOk()
        ->assertJsonPath('set_id', $set->id);

    $slot->refresh();

    expect($set->fresh()->jam_session_id)->toBe($jamSession->id)
        ->and($slot->user_id)->toBeNull()
        ->and((bool) $slot->is_claimable_manual)->toBeFalse();
});

test('scheduling can keep slots assigned to not-going participants as claimable', function () {
    $owner = User::factory()->create();
    $notGoingPerformer = User::factory()->create();

    $jamSession = JamSession::query()->create([
        'name' => 'Claimable Not Going Jam',
        'date' => now()->addDays(14),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Claimable Not Going Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$notGoingPerformer->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'candidate_session_ids' => [$jamSession->id],
    ]);

    $song = $set->songs()->create([
        'artist' => 'Massive Attack',
        'title' => 'Teardrop',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $notGoingPerformer->id,
        'is_claimable_manual' => false,
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $owner->id,
        'status' => JamSessionAttendance::STATUS_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    JamSessionAttendance::query()->create([
        'jam_session_id' => $jamSession->id,
        'user_id' => $notGoingPerformer->id,
        'status' => JamSessionAttendance::STATUS_NOT_GOING,
        'source' => JamSessionAttendance::SOURCE_SELF,
        'status_changed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.schedule', $set), [
            'jam_session_id' => $jamSession->id,
            'not_going_slot_action' => 'keep_claimable',
        ])
        ->assertOk()
        ->assertJsonPath('set_id', $set->id);

    $slot->refresh();

    expect($set->fresh()->jam_session_id)->toBe($jamSession->id)
        ->and($slot->user_id)->toBe($notGoingPerformer->id)
        ->and((bool) $slot->is_claimable_manual)->toBeTrue();
});

test('a manager can add a song with manual slots to a planned set', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Planned Rehearsal',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.songs.store', $set), [
            'artist' => 'Snarky Puppy',
            'title' => 'Lingus',
            'notes' => 'Bring the long outro.',
            'slot_names' => ['keys', 'drums'],
        ])
        ->assertCreated()
        ->assertJsonPath('song.artist', 'Snarky Puppy')
        ->assertJsonPath('song.title', 'Lingus')
        ->assertJsonPath('song.slots.0.name', 'keys')
        ->assertJsonPath('song.slots.1.name', 'drums');

    $song = Song::query()->where('set_id', $set->id)->firstOrFail();

    expect($song->slots()->count())->toBe(2);
});

test('a manager can add slots from a template to a planned set song', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Weekend Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Vulfpeck',
        'title' => 'Dean Town',
        'position' => 1,
    ]);

    $template = BandTemplate::query()->create([
        'name' => 'Core Band',
    ]);

    $template->slots()->createMany([
        ['name' => 'bass'],
        ['name' => 'drums'],
    ]);

    $this->actingAs($owner)
        ->postJson(route('planned-sets.slots.store', ['set' => $set, 'song' => $song]), [
            'addition_mode' => 'template',
            'band_template_id' => $template->id,
        ])
        ->assertCreated()
        ->assertJsonPath('song.id', $song->id);

    $slotNames = Slot::query()
        ->where('song_id', $song->id)
        ->orderBy('position')
        ->pluck('name')
        ->all();

    expect($slotNames)->toBe(['bass', 'drums']);
});

test('a collaborator can take an open planned slot', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Acoustic Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$collaborator->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'signups_open' => true,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Fleetwood Mac',
        'title' => 'Dreams',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'drums',
        'position' => 1,
    ]);

    $this->actingAs($collaborator)
        ->postJson(route('planned-sets.slots.take', ['set' => $set, 'slot' => $slot]))
        ->assertOk()
        ->assertJsonPath('song.id', $song->id)
        ->assertJsonPath('song.slots.0.id', $slot->id)
        ->assertJsonPath('song.slots.0.user_id', $collaborator->id);

    expect($slot->refresh()->user_id)->toBe($collaborator->id);
});

test('a planned set manager can edit a slot assignment and metadata', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Editable Slot Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Toto',
        'title' => 'Rosanna',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'guitar',
        'position' => 1,
        'notes' => 'Original note',
    ]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.slots.update', ['set' => $set, 'slot' => $slot]), [
            'name' => 'keys',
            'notes' => 'Updated note',
            'user_id' => $assignee->id,
            'manual_performer_name' => '',
        ])
        ->assertOk()
        ->assertJsonPath('slot.id', $slot->id)
        ->assertJsonPath('slot.name', 'keys')
        ->assertJsonPath('slot.user_id', $assignee->id)
        ->assertJsonPath('slot.manual_performer_name', null)
        ->assertJsonPath('song.id', $song->id);

    $slot->refresh();

    expect($slot->name)->toBe('keys')
        ->and($slot->notes)->toBe('Updated note')
        ->and($slot->user_id)->toBe($assignee->id)
        ->and($slot->manual_performer_name)->toBeNull();
});

test('editing a planned slot reports and resolves conflicting user assignments on the same song', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();

    $keys = SlotType::query()->firstOrCreate(
        ['key' => 'keys'],
        ['name' => 'Keys', 'sort_order' => 1, 'active' => true]
    );
    $keys->update(['active' => true]);

    $leadGuitar = SlotType::query()->firstOrCreate(
        ['key' => 'lead_guitar'],
        ['name' => 'Lead Guitar', 'sort_order' => 2, 'active' => true]
    );
    $leadGuitar->update(['active' => true]);

    $keys->conflicts()->syncWithoutDetaching([$leadGuitar->id]);

    $set = Set::query()->create([
        'name' => 'Conflict Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Steely Dan',
        'title' => 'Peg',
        'position' => 1,
    ]);

    $slotA = $song->slots()->create([
        'name' => 'keys',
        'position' => 1,
        'user_id' => $assignee->id,
    ]);

    $slotB = $song->slots()->create([
        'name' => 'lead_guitar',
        'position' => 2,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.slots.update', ['set' => $set, 'slot' => $slotB]), [
            'name' => 'lead_guitar',
            'notes' => null,
            'user_id' => $assignee->id,
            'manual_performer_name' => '',
        ])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'conflict' => ['slot_id', 'slot_label']]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.slots.update', ['set' => $set, 'slot' => $slotB]), [
            'name' => 'lead_guitar',
            'notes' => null,
            'user_id' => $assignee->id,
            'manual_performer_name' => '',
            'replace_conflicting_assignment' => true,
        ])
        ->assertOk()
        ->assertJsonPath('slot.id', $slotB->id)
        ->assertJsonPath('slot.user_id', $assignee->id);

    expect($slotA->fresh()->user_id)->toBeNull()
        ->and($slotA->manual_performer_name)->toBeNull()
        ->and($slotB->fresh()->user_id)->toBe($assignee->id);
});

test('a collaborator can request an already assigned planned slot', function () {
    $owner = User::factory()->create();
    $currentPerformer = User::factory()->create();
    $collaborator = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Sunday Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$collaborator->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'signups_open' => true,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Tom Misch',
        'title' => 'Movie',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'bass',
        'position' => 1,
        'user_id' => $currentPerformer->id,
    ]);

    $this->actingAs($collaborator)
        ->postJson(route('planned-sets.slots.request', ['set' => $set, 'slot' => $slot]), [
            'message' => 'Would love to try this groove.',
        ])
        ->assertCreated()
        ->assertJsonPath('song.id', $song->id);

    $requestAssignment = SlotAssignment::query()
        ->where('slot_id', $slot->id)
        ->where('actor_user_id', $collaborator->id)
        ->where('target_user_id', $collaborator->id)
        ->where('type', SlotAssignment::TYPE_REQUEST)
        ->first();

    expect($requestAssignment)->not->toBeNull()
        ->and($requestAssignment->status)->toBe(SlotAssignment::STATUS_PENDING);
});

test('a non collaborator can take an open planned slot when set is free for all', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Community Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'free_for_all' => true,
        'signups_open' => true,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Parcels',
        'title' => 'Tieduprightnow',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'bass',
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.take', ['set' => $set, 'slot' => $slot]))
        ->assertOk()
        ->assertJsonPath('song.id', $song->id)
        ->assertJsonPath('song.slots.0.user_id', $guest->id);

    expect($slot->refresh()->user_id)->toBe($guest->id);
});

test('a non collaborator cannot take an open planned slot when set is not free for all', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Closed Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'free_for_all' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Khruangbin',
        'title' => 'Time (You and I)',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'drums',
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.take', ['set' => $set, 'slot' => $slot]))
        ->assertForbidden();

    expect($slot->refresh()->user_id)->toBeNull();
});

test('a non collaborator can request an open planned slot when set is not free for all', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Requestable Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'free_for_all' => false,
        'signups_open' => true,
    ]);

    $song = $set->songs()->create([
        'artist' => 'The Internet',
        'title' => 'Come Over',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'keys',
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.request', ['set' => $set, 'slot' => $slot]), [
            'message' => 'Happy to play this part.',
        ])
        ->assertCreated();

    $requestAssignment = SlotAssignment::query()
        ->where('slot_id', $slot->id)
        ->where('actor_user_id', $guest->id)
        ->where('target_user_id', $guest->id)
        ->where('type', SlotAssignment::TYPE_REQUEST)
        ->where('status', SlotAssignment::STATUS_PENDING)
        ->first();

    expect($requestAssignment)->not->toBeNull();
});

test('a non manager cannot take or request or propose slots when sign ups are closed', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $recommendedUser = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Closed Signups Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'collaborator_ids' => [$recommendedUser->id],
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
        'free_for_all' => true,
        'signups_open' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Khruangbin',
        'title' => 'Evan Finds the Third Room',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'guitar',
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.take', ['set' => $set, 'slot' => $slot]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sign ups are closed for this set.');

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.request', ['set' => $set, 'slot' => $slot]), [
            'message' => 'Please let me cover this one.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sign ups are closed for this set.');

    $this->actingAs($guest)
        ->postJson(route('planned-sets.slots.propose', ['set' => $set, 'slot' => $slot]), [
            'target_user_id' => $recommendedUser->id,
            'message' => 'Recommend this part to another collaborator.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sign ups are closed for this set.');

    expect($slot->refresh()->user_id)->toBeNull();
});

test('a planned set manager can approve a pending song request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Approval Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $songRequest = SongRequest::query()->create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Mayer Hawthorne',
        'title' => 'The Walk',
        'notes' => 'Great opener.',
        'requested_slot_names' => ['vocals'],
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.song-requests.respond', ['set' => $set, 'songRequest' => $songRequest]), [
            'status' => SongRequest::STATUS_ACCEPTED,
            'approved_slot_names' => ['vocals'],
        ])
        ->assertOk()
        ->assertJsonPath('song_request_id', $songRequest->id)
        ->assertJsonPath('song.artist', 'Mayer Hawthorne')
        ->assertJsonPath('song.title', 'The Walk');

    $createdSong = Song::query()
        ->where('set_id', $set->id)
        ->where('artist', 'Mayer Hawthorne')
        ->where('title', 'The Walk')
        ->first();

    expect($songRequest->refresh()->status)->toBe(SongRequest::STATUS_ACCEPTED)
        ->and($songRequest->song_id)->not->toBeNull()
        ->and($createdSong)->not->toBeNull()
        ->and($createdSong?->slots()->where('name', 'vocals')->first()?->user_id)->toBe($requester->id);
});

test('a planned set manager can approve a pending slot request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Slot Approval Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Daft Punk',
        'title' => 'Get Lucky',
        'position' => 1,
    ]);

    $slot = $song->slots()->create([
        'name' => 'guitar',
        'position' => 1,
    ]);

    $slotRequest = SlotAssignment::query()->create([
        'slot_id' => $slot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.slot-assignments.respond', ['set' => $set, 'slotAssignment' => $slotRequest]), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertOk()
        ->assertJsonPath('slot_assignment_id', $slotRequest->id)
        ->assertJsonPath('song.id', $song->id)
        ->assertJsonPath('song.slots.0.user_id', $requester->id);

    expect($slotRequest->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED)
        ->and($slot->refresh()->user_id)->toBe($requester->id);
});

test('a planned set manager can edit a song', function () {
    $owner = User::factory()->create();

    $set = Set::query()->create([
        'name' => 'Song Edit Draft',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => null,
        'lifecycle_state' => Set::LIFECYCLE_DRAFT,
        'position' => 0,
        'performed' => false,
        'is_hidden' => false,
    ]);

    $song = $set->songs()->create([
        'artist' => 'Old Artist',
        'title' => 'Old Title',
        'notes' => 'Old notes',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('planned-sets.songs.update', ['set' => $set, 'song' => $song]), [
            'artist' => 'New Artist',
            'title' => 'New Title',
            'notes' => 'Fresh notes',
            'duration' => 245,
            'source' => 'deezer',
        ])
        ->assertOk()
        ->assertJsonPath('song.id', $song->id)
        ->assertJsonPath('song.artist', 'New Artist')
        ->assertJsonPath('song.title', 'New Title')
        ->assertJsonPath('song.notes', 'Fresh notes')
        ->assertJsonPath('song.duration', 245)
        ->assertJsonPath('song.source', 'deezer');

    expect($song->refresh()->artist)->toBe('New Artist')
        ->and($song->title)->toBe('New Title')
        ->and($song->notes)->toBe('Fresh notes')
        ->and($song->duration)->toBe(245)
        ->and($song->source)->toBe('deezer');
});
