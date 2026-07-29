<?php

use App\Models\Attachment;
use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSessionSet(User $owner): array
{
    $session = JamSession::query()->create([
        'name' => 'Attachment Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Attachment Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    return [$session, $set];
}

test('set attachments allow view-only users and manager write access', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    [, $set] = makeSessionSet($owner);

    $this->actingAs($owner)
        ->postJson(route('sets.attachments.store', $set), [
            'type' => Attachment::TYPE_LINK,
            'label' => 'Chart',
            'url' => 'https://example.com/chart',
        ])
        ->assertCreated()
        ->assertJsonPath('attachments.0.type', Attachment::TYPE_LINK);

    $this->actingAs($viewer)
        ->getJson(route('sets.attachments.index', $set))
        ->assertOk()
        ->assertJsonPath('can_manage', false)
        ->assertJsonPath('attachments.0.label', 'Chart');

    $this->actingAs($viewer)
        ->postJson(route('sets.attachments.store', $set), [
            'type' => Attachment::TYPE_LINK,
            'url' => 'https://example.com/other',
        ])
        ->assertForbidden();
});

test('song attachments can be managed by assigned slot users', function () {
    $owner = User::factory()->create();
    $assignedUser = User::factory()->create();
    $viewer = User::factory()->create();

    [, $set] = makeSessionSet($owner);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Song',
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $assignedUser->id,
    ]);

    $this->actingAs($assignedUser)
        ->postJson(route('songs.attachments.store', $song), [
            'type' => Attachment::TYPE_LINK,
            'label' => 'Reference',
            'url' => 'https://example.com/reference',
        ])
        ->assertCreated();

    $this->actingAs($viewer)
        ->getJson(route('songs.attachments.index', $song))
        ->assertOk()
        ->assertJsonPath('can_manage', false)
        ->assertJsonPath('attachments.0.label', 'Reference');

    $this->actingAs($viewer)
        ->postJson(route('songs.attachments.store', $song), [
            'type' => Attachment::TYPE_LINK,
            'url' => 'https://example.com/fail',
        ])
        ->assertForbidden();
});

test('slot attachments can be managed by the assigned slot performer', function () {
    $owner = User::factory()->create();
    $slotUser = User::factory()->create();
    $viewer = User::factory()->create();

    [, $set] = makeSessionSet($owner);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Song',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $slotUser->id,
    ]);

    $response = $this->actingAs($slotUser)
        ->postJson(route('slots.attachments.store', $slot), [
            'type' => Attachment::TYPE_LINK,
            'label' => 'Notes',
            'url' => 'https://example.com/notes',
        ])
        ->assertCreated();

    $attachmentId = $response->json('attachments.0.id');

    $this->actingAs($viewer)
        ->postJson(route('slots.attachments.store', $slot), [
            'type' => Attachment::TYPE_LINK,
            'url' => 'https://example.com/fail',
        ])
        ->assertForbidden();

    $this->actingAs($slotUser)
        ->deleteJson(route('attachments.destroy', $attachmentId))
        ->assertOk();
});

test('admin can browse attachments administration page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $nonAdmin = User::factory()->create();

    [$session, $set] = makeSessionSet($owner);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Breadcrumb Artist',
        'title' => 'Breadcrumb Song',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $set->attachments()->create([
        'uploader_user_id' => $owner->id,
        'type' => Attachment::TYPE_LINK,
        'label' => 'Set Admin Visible',
        'url' => 'https://example.com/admin-visible',
    ]);

    $song->attachments()->create([
        'uploader_user_id' => $owner->id,
        'type' => Attachment::TYPE_FILE,
        'label' => 'Song Admin Visible',
        'original_filename' => 'chart.pdf',
        'disk' => 'local',
        'path' => 'attachments/chart.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]);

    $slot->attachments()->create([
        'uploader_user_id' => $owner->id,
        'type' => Attachment::TYPE_LINK,
        'label' => 'Slot Admin Visible',
        'url' => 'https://example.com/slot-visible',
    ]);

    $sessionUrl = route('sessions.show', $session);

    $this->actingAs($admin)
        ->get(route('admin.attachments.index'))
        ->assertOk()
        ->assertSee('attachments found')
        ->assertSee('Set Admin Visible')
        ->assertSee('Song Admin Visible')
        ->assertSee('Slot Admin Visible')
        ->assertSee('2.0 KB')
        ->assertSee('by '.$owner->name)
        ->assertSee('href="'.$sessionUrl.'"', false)
        ->assertSee('href="'.$sessionUrl.'#set-'.$set->id.'"', false)
        ->assertSee('href="'.$sessionUrl.'#song-'.$song->id.'"', false)
        ->assertSee('href="'.$sessionUrl.'#slot-'.$slot->id.'"', false)
        ->assertSee('Delete attachment', false)
        ->assertDontSee('>Uploader<', false)
        ->assertDontSee('>LINK<', false)
        ->assertDontSee('>FILE<', false);

    $this->actingAs($nonAdmin)
        ->get(route('admin.attachments.index'))
        ->assertForbidden();
});
