<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('set and song cards render dropdown menu controls', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::create([
        'name' => 'Menu Set',
        'description' => 'Menu description',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Menu Artist',
        'title' => 'Menu Song',
        'notes' => null,
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'manual_performer_name' => 'Guest Singer',
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('aria-label="Set actions"', false)
        ->assertSee('Live Summary')
        ->assertSee('Edit Set')
        ->assertSee('Add Song')
        ->assertSee('Set Snapshot')
        ->assertSee('Attachments')
        ->assertSee('#set-'.$set->id, false);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee('aria-label="Song actions"', false)
        ->assertSee('Edit Song')
        ->assertSee('Add Slot')
        ->assertSee('Clear Slot')
        ->assertSee('Edit slot')
        ->assertDontSee('Request slot')
        ->assertSee('Copy Direct Link')
        ->assertSee('Attachments')
        ->assertSee('#song-'.$song->id, false)
        ->assertSee('#slot-'.$slot->id, false);
});

test('set action menu offers a table image export', function () {
    $shell = file_get_contents(resource_path('views/components/sessions/set-card-shell.blade.php'));
    $songCard = file_get_contents(resource_path('views/components/sessions/song-card.blade.php'));
    $slotActionMenu = file_get_contents(resource_path('views/components/sessions/slot-action-menu.blade.php'));
    $slotRow = file_get_contents(resource_path('views/components/sessions/slot-row.blade.php'));
    $script = file_get_contents(resource_path('js/components/sessionCards.js'));
    $summaryModal = file_get_contents(resource_path('views/components/sessions/set-summary-modal.blade.php'));
    $snapshotModal = file_get_contents(resource_path('views/components/sessions/set-snapshot-modal.blade.php'));

    expect($shell)
        ->toContain('aria-label="Open set attachments"')
        ->toContain('Set Snapshot')
        ->toContain('x-heroicon-m-photo')
        ->toContain('openSnapshotModal()')
        ->toContain('openAttachmentsModal()')
        ->toContain('x-heroicon-m-paper-clip')
        ->toContain('<x-sessions.set-snapshot-modal />')
        ->toContain('<x-sessions.attachments-modal />')
        ->toContain("'sessionDate' => \$set->session->date->format('M j, Y')");

    expect($songCard)
        ->toContain('aria-label="Open song attachments"')
        ->toContain('openAttachmentsModal()')
        ->toContain('x-heroicon-m-paper-clip')
        ->toContain('attachmentsListUrl')
        ->toContain('attachmentsStoreUrl');

    expect($slotActionMenu)
        ->toContain('openAttachmentsModal()')
        ->toContain('x-heroicon-m-paper-clip')
        ->toContain('Attachments')
        ->toContain("slotIsManuallyClaimable ? 'Remove claimable status' : 'Mark slot claimable'")
        ->toContain('{{ $slotManageMenuItemClass }}')
        ->toContain('<x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />');

    expect(strpos($slotActionMenu, 'Edit slot'))->toBeLessThan(strpos($slotActionMenu, 'Mark slot claimable'));

    expect($slotRow)
        ->toContain('aria-label="Open slot attachments"')
        ->toContain('x-heroicon-m-paper-clip')
        ->toContain('attachmentsListUrl')
        ->toContain('attachmentsStoreUrl')
        ->toContain('<x-sessions.attachments-modal />');

    expect($script)
        ->toContain('renderSetSummaryImage')
        ->toContain('copySnapshotImage')
        ->toContain('downloadSnapshotImage')
        ->toContain('shareSnapshotImage')
        ->toContain('hasUncommittedAttachmentDraft')
        ->toContain('You may not have finished adding an attachment. Do you want to continue editing?')
        ->toContain('attachmentFileInput')
        ->toContain('this.closeSnapshotModal()')
        ->toContain('this.snapshotImageUrl = URL.createObjectURL(blob)')
        ->toContain('drawPersonIcon')
        ->toContain('drawCalendarIcon')
        ->toContain('drawBackstageLogo')
        ->toContain('drawAssignmentPill')
        ->toContain('const footerHeight = 40')
        ->toContain('const footerTop = height - footerHeight')
        ->toContain('const headerPanelHeight = 78')
        ->toContain('context.roundRect(padding, headerPanelTop')
        ->toContain('context.fillText(window.location.hostname')
        ->toContain("context.fillStyle = '#e2e8f0'")
        ->toContain("context.fillStyle = '#0ea5e9'")
        ->toContain("rowIndex % 2 === 0 ? '#ffffff' : '#f8fafc'")
        ->toContain("'image/png'")
        ->toContain('new ClipboardItem');

    expect($script)->not->toContain('context.moveTo(padding, footerTop)');

    expect($summaryModal)->toContain('summaryData?.songs ?? []');

    expect($shell)
        ->toContain('x-show="shareCopied || directLinkCopied"')
        ->not->toContain('Set table image copied');

    expect($snapshotModal)
        ->toContain('aria-label="Set snapshot"')
        ->not->toContain('Ready for the group chat');
});

test('slot assignment user lists exclude deleted users', function () {
    $owner = User::factory()->create();
    $activePerformer = User::factory()->create(['name' => 'Active Performer']);
    User::factory()->create([
        'name' => 'Deleted Performer',
        'is_deleted_account' => true,
        'deleted_account_at' => now(),
    ]);

    $session = JamSession::query()->create([
        'name' => 'Deleted User Filter Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::create([
        'name' => 'Deleted User Filter Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Filter Artist',
        'title' => 'Filter Song',
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee($activePerformer->name)
        ->assertDontSee('Deleted Performer');
});

test('set summary exports actual assignee names for the viewing performer', function () {
    $owner = User::factory()->create();
    $performer = User::factory()->create(['name' => 'Actual Performer']);
    $session = JamSession::query()->create([
        'name' => 'Shareable Setlist Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);
    $set = Set::query()->create([
        'name' => 'Shareable Setlist',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);
    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'The Performers',
        'title' => 'Actual Names',
        'notes' => null,
        'position' => 1,
    ]);
    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'user_id' => $performer->id,
        'position' => 1,
    ]);

    $this->actingAs($performer)
        ->getJson(route('sets.summary', $set))
        ->assertOk()
        ->assertJsonPath('songs.0.slot_map.vocals.display', 'Actual Performer')
        ->assertJsonPath('songs.0.slot_map.vocals.is_current_user', true);
});

test('mobile set cards keep the organiser line concise', function () {
    $owner = User::factory()->create(['name' => 'Set Owner']);
    $collaboratorOne = User::factory()->create(['name' => 'First Collaborator']);
    $collaboratorTwo = User::factory()->create(['name' => 'Second Collaborator']);

    $session = JamSession::query()->create([
        'name' => 'Mobile Line Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::create([
        'name' => 'Mobile Line Set',
        'description' => 'Mobile line description',
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
        'collaborator_ids' => [$collaboratorOne->id, $collaboratorTwo->id],
    ]);

    Song::create([
        'set_id' => $set->id,
        'artist' => 'Mobile Artist',
        'title' => 'Mobile Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Set Owner')
        ->assertSee('and collaborators')
        ->assertSee('hidden md:inline', false);
});

test('admin sees shield suffix on managed set and song menu items', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['name' => 'Set Owner']);

    $session = JamSession::query()->create([
        'name' => 'Admin Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::create([
        'name' => 'Admin Menu Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Admin Artist',
        'title' => 'Admin Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $set->songs()->sole()->id,
        'name' => 'guitar',
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('text-sky-700 hover:bg-sky-50 focus:bg-sky-50', false)
        ->assertSee('Edit Set')
        ->assertSee('Request Song')
        ->assertDontSee('🛡️');

    $this->actingAs($admin)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee('Edit Song')
        ->assertSee('Add Slot')
        ->assertSee('Take this slot')
        ->assertDontSee('🛡️');
});

test('an admin can request a song from another user\'s set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Admin Song Request Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Song Request Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets', $session), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Request Song')
        ->assertSee('openSongRequestModal()', false)
        ->assertSee('Request a Song for '.$set->name);
});

test('non-manager still sees song actions menu with direct link action', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Guest Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Guest Menu Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Guest Artist',
        'title' => 'Guest Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($guest)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee('aria-label="Song actions"', false)
        ->assertSee('Copy Direct Link')
        ->assertDontSee('Add Slot')
        ->assertDontSee('Edit Song');
});

test('manual slots count toward set health on session cards', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::query()->create([
        'name' => 'Health Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::create([
        'name' => 'Health Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
    ]);

    $song = Song::create([
        'set_id' => $set->id,
        'artist' => 'Health Artist',
        'title' => 'Health Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'manual_performer_name' => 'Guest Vocalist',
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertSee('Set health: 1/1 slots filled');
});

test('admin does not see shield suffix on their own set menu items', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $session = JamSession::query()->create([
        'name' => 'Admin Own Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Admin Own Menu Set',
        'description' => null,
        'owner_id' => $admin->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Own Artist',
        'title' => 'Own Song',
        'notes' => null,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.sets', $session))
        ->assertOk()
        ->assertDontSee('mr-1 inline h-4 w-4 text-sky-500', false)
        ->assertDontSee('🛡️')
        ->assertDontSee('sr-only"> Admin action</span>', false);
});

test('clear slot action is hidden when the current user has the slot', function () {
    $owner = User::factory()->create();

    $session = JamSession::query()->create([
        'name' => 'Owner Slot Menu Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
        'allow_checkins' => true,
    ]);

    $set = Set::query()->create([
        'name' => 'Owner Slot Menu Set',
        'description' => null,
        'owner_id' => $owner->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Owner Slot Artist',
        'title' => 'Owner Slot Song',
        'notes' => null,
        'position' => 1,
    ]);

    Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('sessions.sets.body', [$session, $set]))
        ->assertOk()
        ->assertSee('Release slot')
        ->assertSee('Clear Slot')
        ->assertSee('x-show="!slotIsOpen && !assignedToCurrentUser"', false);
});
