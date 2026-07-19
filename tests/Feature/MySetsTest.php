<?php

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('my sets page shows combined pending work for owner and signup sets', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $ownedSet = Set::create([
        'name' => 'Owned Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $otherSet = Set::create([
        'name' => 'Other Set',
        'description' => null,
        'owner_id' => $other->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
    ]);

    $ownedSong = Song::create([
        'set_id' => $ownedSet->id,
        'artist' => 'Band A',
        'title' => 'Track A',
        'notes' => null,
        'position' => 1,
    ]);

    $otherSong = Song::create([
        'set_id' => $otherSet->id,
        'artist' => 'Band B',
        'title' => 'Track B',
        'notes' => null,
        'position' => 1,
    ]);

    $ownedSlot = Slot::create([
        'song_id' => $ownedSong->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $otherSlot = Slot::create([
        'song_id' => $otherSong->id,
        'name' => 'drums',
        'position' => 1,
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $ownedSlot->id,
        'actor_user_id' => $other->id,
        'target_user_id' => $other->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    SongRequest::create([
        'set_id' => $ownedSet->id,
        'requester_user_id' => $other->id,
        'artist' => 'Requested Artist',
        'title' => 'Requested Song',
        'notes' => 'Please add this one.',
        'status' => SongRequest::STATUS_PENDING,
    ]);

    SlotAssignment::create([
        'slot_id' => $otherSlot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $owner->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('My Sets')
        ->assertSee('Approvals')
        ->assertSee('Owned Set')
        ->assertSee('Band A - Track A')
        ->assertSee('Requested Artist - Requested Song')
        ->assertSee('Song request')
        ->assertSee('Pending for you')
        ->assertSee('Other Set')
        ->assertSee('Band B - Track B');
});

test('my sets count endpoint returns pending approval count', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Count Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $ownedSet = Set::create([
        'name' => 'Count Owned Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $otherSet = Set::create([
        'name' => 'Count Other Set',
        'description' => null,
        'owner_id' => $other->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
    ]);

    $ownedSong = Song::create([
        'set_id' => $ownedSet->id,
        'artist' => 'Count Band',
        'title' => 'Approval Song',
        'notes' => null,
        'position' => 1,
    ]);

    $otherSong = Song::create([
        'set_id' => $otherSet->id,
        'artist' => 'Count Band',
        'title' => 'Consent Song',
        'notes' => null,
        'position' => 1,
    ]);

    $ownedSlot = Slot::create([
        'song_id' => $ownedSong->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $otherSlot = Slot::create([
        'song_id' => $otherSong->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $ownedSlot->id,
        'actor_user_id' => $other->id,
        'target_user_id' => $other->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    SlotAssignment::create([
        'slot_id' => $otherSlot->id,
        'actor_user_id' => $other->id,
        'target_user_id' => $owner->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    SongRequest::create([
        'set_id' => $ownedSet->id,
        'requester_user_id' => $other->id,
        'artist' => 'Count Request Artist',
        'title' => 'Count Request Song',
        'notes' => null,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $this->getJson(route('my-sets.count'))
        ->assertRedirect(route('login'));

    $this->actingAs($owner)
        ->getJson(route('my-sets.count'))
        ->assertOk()
        ->assertJson([
            'count' => 3,
        ]);
});

test('my sets approval card warns when approval will move a player from a conflicting slot', function () {
    $owner = User::factory()->create();
    $player = User::factory()->create(['name' => 'Conflicted Player']);

    $session = JamSession::create([
        'name' => 'Conflict Warning Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Conflict Warning Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Conflict Band',
        'title' => 'Conflict Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::create([
        'song_id' => $song->id,
        'name' => 'keys',
        'position' => 1,
        'user_id' => $player->id,
    ]);

    $drumsSlot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 2,
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $drumsSlot->id,
        'actor_user_id' => $player->id,
        'target_user_id' => $player->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('Approving this will move Conflicted Player from Keys to Drums on this song.');
});

test('song request approval card includes band template selector', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Template Selector Session',
        'date' => now()->addDays(2),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Template Selector Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    SongRequest::create([
        'set_id' => $set->id,
        'requester_user_id' => $requester->id,
        'artist' => 'Selector Artist',
        'title' => 'Selector Song',
        'notes' => null,
        'status' => SongRequest::STATUS_PENDING,
    ]);

    $template = BandTemplate::create(['name' => 'Template Five Piece']);
    $template->slots()->create(['name' => 'vocals']);

    $this->actingAs($owner)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('Band template (optional)')
        ->assertSee('name="band_template_id"', false)
        ->assertSee('Template Five Piece');
});

test('set owner can accept proposal assignment for their set', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Proposal Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Proposal Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Band X',
        'title' => 'Track X',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $assignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($slot->refresh()->user_id)->toBe($target->id);
});

test('proposal assignment requires target consent before set owner approval', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Consent Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Consent Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Consent Band',
        'title' => 'Consent Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'keys',
        'position' => 1,
        'user_id' => null,
    ]);

    $this->actingAs($actor)
        ->post(route('slot-assignments.propose', $slot), [
            'target_user_id' => $target->id,
            'message' => 'You would be great here.',
        ])
        ->assertRedirect();

    $assignment = SlotAssignment::query()->firstOrFail();

    expect($assignment->status)->toBe(SlotAssignment::STATUS_AWAITING_TARGET_CONSENT);

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertForbidden();

    expect($slot->refresh()->user_id)->toBeNull();

    $this->actingAs($target)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_PENDING);
    expect($slot->refresh()->user_id)->toBeNull();

    $this->actingAs($owner)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($slot->refresh()->user_id)->toBe($target->id);
});

test('owner recommendation is assigned when target accepts', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Owner Recommendation Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Owner Recommendation Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Owner Consent Band',
        'title' => 'One Step Approval',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'lead_guitar',
        'position' => 1,
        'user_id' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('slot-assignments.propose', $slot), [
            'target_user_id' => $target->id,
            'message' => 'Can you take this one?',
        ])
        ->assertRedirect();

    $assignment = SlotAssignment::query()->firstOrFail();

    expect($assignment->status)->toBe(SlotAssignment::STATUS_AWAITING_TARGET_CONSENT);

    $this->actingAs($target)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($slot->refresh()->user_id)->toBe($target->id);
});

test('accepting owner recommendation rejects target self requests on the same song', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Superseded Request Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Superseded Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Request Band',
        'title' => 'Choose A Slot',
        'notes' => null,
        'position' => 1,
    ]);

    $requestedSlot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $recommendedSlot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 2,
        'user_id' => null,
    ]);

    $selfRequest = SlotAssignment::create([
        'slot_id' => $requestedSlot->id,
        'actor_user_id' => $target->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $recommendation = SlotAssignment::create([
        'slot_id' => $recommendedSlot->id,
        'actor_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    $this->actingAs($target)
        ->patch(route('slot-assignments.respond', $recommendation), [
            'status' => SlotAssignment::STATUS_ACCEPTED,
        ])
        ->assertRedirect();

    expect($recommendation->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($recommendedSlot->refresh()->user_id)->toBe($target->id);
    expect($selfRequest->refresh()->status)->toBe(SlotAssignment::STATUS_REJECTED);
    expect($selfRequest->responded_at)->not->toBeNull();
});

test('editing a slot assignee accepts matching pending assignments', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $otherTarget = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Edit Slot Approval Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Edit Slot Approval Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Approval Band',
        'title' => 'Direct Assignment',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $matchingAssignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $target->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $otherAssignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $otherTarget->id,
        'target_user_id' => $otherTarget->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('slots.update', $slot), [
            'name' => $slot->name,
            'user_id' => $target->id,
            'manual_performer_name' => null,
            'position' => $slot->position,
        ])
        ->assertRedirect();

    expect($slot->refresh()->user_id)->toBe($target->id);
    expect($matchingAssignment->refresh()->status)->toBe(SlotAssignment::STATUS_ACCEPTED);
    expect($matchingAssignment->responded_at)->not->toBeNull();
    expect($otherAssignment->refresh()->status)->toBe(SlotAssignment::STATUS_PENDING);
});

test('target can reject proposal assignment before organiser approval', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Reject Consent Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Reject Consent Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Consent Band',
        'title' => 'No Thanks',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $assignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
    ]);

    $this->actingAs($target)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_REJECTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_REJECTED);
    expect($slot->refresh()->user_id)->toBeNull();
});

test('target can cancel proposal assignment after consenting before organiser approval', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Cancel Pending Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Cancel Pending Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Consent Band',
        'title' => 'Changed Mind',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $assignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($target)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_REJECTED,
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_REJECTED);
    expect($slot->refresh()->user_id)->toBeNull();
});

test('recommender cannot cancel proposal assignment after target consent', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $session = JamSession::create([
        'name' => 'Actor Cancel Session',
        'date' => now()->addDays(3),
        'description' => null,
    ]);

    $set = Set::create([
        'name' => 'Actor Cancel Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Consent Band',
        'title' => 'Not Your Call',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::create([
        'song_id' => $song->id,
        'name' => 'drums',
        'position' => 1,
        'user_id' => null,
    ]);

    $assignment = SlotAssignment::create([
        'slot_id' => $slot->id,
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
    ]);

    $this->actingAs($actor)
        ->patch(route('slot-assignments.respond', $assignment), [
            'status' => SlotAssignment::STATUS_REJECTED,
        ])
        ->assertForbidden();

    expect($assignment->refresh()->status)->toBe(SlotAssignment::STATUS_PENDING);
    expect($slot->refresh()->user_id)->toBeNull();
});

test('my sets combines owned sets signed up songs and pending assignments', function () {
    $user = User::factory()->create(['name' => 'Practice Player']);
    $setOwner = User::factory()->create(['name' => 'Set Owner']);
    $requester = User::factory()->create(['name' => 'Slot Requester']);
    $recommender = User::factory()->create(['name' => 'Recommender']);
    $otherUser = User::factory()->create(['name' => 'Other Player']);

    $session = JamSession::create([
        'name' => 'Practice Jam',
        'date' => now()->addDays(4),
        'description' => null,
    ]);

    $hiddenSession = JamSession::create([
        'name' => 'Hidden Practice Jam',
        'date' => now()->addDays(5),
        'description' => null,
        'is_hidden' => true,
    ]);

    $closedSession = JamSession::create([
        'name' => 'Closed Practice Jam',
        'date' => now()->addDays(6),
        'description' => null,
        'is_closed' => true,
    ]);

    $ownedSet = Set::create([
        'name' => 'Owned Practice Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $signedSet = Set::create([
        'name' => 'Signed Practice Set',
        'description' => null,
        'owner_id' => $setOwner->id,
        'jam_session_id' => $session->id,
        'position' => 2,
        'performed' => false,
        'signups_open' => true,
    ]);

    $pendingSet = Set::create([
        'name' => 'Pending Practice Set',
        'description' => null,
        'owner_id' => $setOwner->id,
        'jam_session_id' => $session->id,
        'position' => 3,
        'performed' => false,
        'signups_open' => true,
    ]);

    $ownedSetWithoutSlots = Set::create([
        'name' => 'Owned Set Without My Slots',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 4,
        'performed' => false,
        'signups_open' => true,
    ]);

    $performedSet = Set::create([
        'name' => 'Already Played Set',
        'description' => null,
        'owner_id' => $setOwner->id,
        'jam_session_id' => $session->id,
        'position' => 5,
        'performed' => true,
        'signups_open' => true,
    ]);

    $hiddenSessionSet = Set::create([
        'name' => 'Hidden Session Set',
        'description' => null,
        'owner_id' => $setOwner->id,
        'jam_session_id' => $hiddenSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $closedSessionSet = Set::create([
        'name' => 'Closed Session Set',
        'description' => null,
        'owner_id' => $setOwner->id,
        'jam_session_id' => $closedSession->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $ownedSong = Song::create([
        'set_id' => $ownedSet->id,
        'artist' => 'Owner Band',
        'title' => 'Owner Song',
        'notes' => null,
        'position' => 1,
    ]);

    $signedSong = Song::create([
        'set_id' => $signedSet->id,
        'artist' => 'Signed Band',
        'title' => 'Signed Song',
        'notes' => 'Practise the bridge.',
        'position' => 1,
    ]);

    $pendingSong = Song::create([
        'set_id' => $pendingSet->id,
        'artist' => 'Pending Band',
        'title' => 'Pending Song',
        'notes' => null,
        'position' => 1,
    ]);

    $ownedSongWithoutSlots = Song::create([
        'set_id' => $ownedSetWithoutSlots->id,
        'artist' => 'Quiet Band',
        'title' => 'Not Mine',
        'notes' => null,
        'position' => 1,
    ]);

    $performedSong = Song::create([
        'set_id' => $performedSet->id,
        'artist' => 'Finished Band',
        'title' => 'Already Done',
        'notes' => null,
        'position' => 1,
    ]);

    $hiddenSessionSong = Song::create([
        'set_id' => $hiddenSessionSet->id,
        'artist' => 'Hidden Band',
        'title' => 'Hidden Song',
        'notes' => null,
        'position' => 1,
    ]);

    $closedSessionSong = Song::create([
        'set_id' => $closedSessionSet->id,
        'artist' => 'Closed Band',
        'title' => 'Closed Song',
        'notes' => null,
        'position' => 1,
    ]);

    $ownedSlot = Slot::create([
        'song_id' => $ownedSong->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    $signedSlot = Slot::create([
        'song_id' => $signedSong->id,
        'name' => 'drums',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    Slot::create([
        'song_id' => $ownedSongWithoutSlots->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => null,
    ]);

    Slot::create([
        'song_id' => $performedSong->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    Slot::create([
        'song_id' => $hiddenSessionSong->id,
        'name' => 'keys',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    Slot::create([
        'song_id' => $closedSessionSong->id,
        'name' => 'rhythm_guitar',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    $hiddenPendingSlot = Slot::create([
        'song_id' => $hiddenSessionSong->id,
        'name' => 'bass',
        'position' => 2,
        'user_id' => null,
    ]);

    $closedPendingSlot = Slot::create([
        'song_id' => $closedSessionSong->id,
        'name' => 'drums',
        'position' => 2,
        'user_id' => null,
    ]);

    $pendingRequestSlot = Slot::create([
        'song_id' => $pendingSong->id,
        'name' => 'bass',
        'position' => 1,
        'user_id' => null,
    ]);

    $pendingProposalSlot = Slot::create([
        'song_id' => $pendingSong->id,
        'name' => 'keys',
        'position' => 2,
        'user_id' => null,
    ]);

    SlotAssignment::create([
        'slot_id' => $ownedSlot->id,
        'actor_user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'Can I sing this one?',
    ]);

    SlotAssignment::create([
        'slot_id' => $pendingRequestSlot->id,
        'actor_user_id' => $user->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'I can cover bass.',
    ]);

    SlotAssignment::create([
        'slot_id' => $pendingProposalSlot->id,
        'actor_user_id' => $recommender->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'You should try keys.',
    ]);

    SlotAssignment::create([
        'slot_id' => $hiddenPendingSlot->id,
        'actor_user_id' => $user->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_REQUEST,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'Hidden session pending request.',
    ]);

    SlotAssignment::create([
        'slot_id' => $closedPendingSlot->id,
        'actor_user_id' => $recommender->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'Closed session pending recommendation.',
    ]);

    SlotAssignment::create([
        'slot_id' => $pendingProposalSlot->id,
        'actor_user_id' => $recommender->id,
        'target_user_id' => $user->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
        'message' => 'Can we put you forward for keys?',
    ]);

    SlotAssignment::create([
        'slot_id' => $pendingProposalSlot->id,
        'actor_user_id' => $recommender->id,
        'target_user_id' => $otherUser->id,
        'type' => SlotAssignment::TYPE_PROPOSAL,
        'status' => SlotAssignment::STATUS_PENDING,
        'message' => 'This belongs elsewhere.',
    ]);

    $this->actingAs($user)
        ->get(route('my-sets.index'))
        ->assertOk()
        ->assertSee('My Sets')
        ->assertSeeInOrder(['href="http://backstage-v1.test/my-sets"', '<span>My Sets</span>', '2'], false)
        ->assertSee(now()->addDays(4)->format('F Y'))
        ->assertSee('Owned Practice Set')
        ->assertSee('Owner Band - Owner Song')
        ->assertSee('Slot Requester')
        ->assertSee('Can I sing this one?')
        ->assertSee('Signed Practice Set')
        ->assertSee('Signed Band - Signed Song')
        ->assertSee('Drums')
        ->assertSee(route('sessions.show', $signedSet->session).'#set-'.$signedSet->id, false)
        ->assertSee(route('sessions.show', $signedSet->session).'#song-'.$signedSong->id, false)
        ->assertSee(route('sessions.show', $signedSet->session).'#slot-'.$signedSlot->id, false)
        ->assertSee('Pending Practice Set')
        ->assertSee('Pending Band - Pending Song')
        ->assertSee('I can cover bass.')
        ->assertSee('You should try keys.')
        ->assertSee('Slot Recommendation')
        ->assertSee('Can we put you forward for keys?')
        ->assertDontSee('Owned Set Without My Slots')
        ->assertDontSee('Quiet Band - Not Mine')
        ->assertDontSee('Already Played Set')
        ->assertDontSee('Finished Band - Already Done')
        ->assertDontSee('Hidden Session Set')
        ->assertDontSee('Hidden Band - Hidden Song')
        ->assertDontSee('Closed Session Set')
        ->assertDontSee('Closed Band - Closed Song')
        ->assertDontSee('Hidden session pending request.')
        ->assertDontSee('Closed session pending recommendation.')
        ->assertDontSee('This belongs elsewhere.');
});
