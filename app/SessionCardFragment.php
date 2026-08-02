<?php

namespace App;

use App\Models\BandTemplate;
use App\Models\JamSessionAttendance;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use App\Services\JamSessionAttendanceService;

class SessionCardFragment
{
    public function __construct(private readonly JamSessionAttendanceService $attendanceService) {}

    public function song(Song $song, User $viewer): string
    {
        $song->loadMissing([
            'slots.user',
            'slots.assignments.actor',
            'slots.assignments.target',
            'set.session',
            'set.owner',
        ]);
        $song->loadCount('attachments');
        $song->slots->each(fn (Slot $slot) => $slot->loadCount('attachments'));

        $set = $song->set;
        $isSetOwner = $set->owner_id === $viewer->id;
        $canManageSet = $viewer->is_admin || $isSetOwner || $set->isCollaborator($viewer);
        $songCount = $set->songs()->count();
        $users = User::query()->orderBy('name')->get();
        $assignmentUsers = $this->attendanceService->assignmentUserOptions($set->session, $users, $viewer);
        $notGoingUserIds = $assignmentUsers
            ->filter(fn (array $user) => $user['attendance_status'] === JamSessionAttendance::STATUS_NOT_GOING)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return view('components.sessions.song-card', [
            'song' => $song,
            'set' => $set,
            'users' => $users,
            'assignmentUsers' => $assignmentUsers,
            'notGoingUserIds' => $notGoingUserIds,
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'slotOptions' => Slot::options(),
            'pendingSlotAssignments' => collect(),
            'jamSessionClosed' => (bool) $set->session?->is_closed,
            'isSetOwner' => $isSetOwner,
            'canManageSet' => $canManageSet,
            'canReorderSongs' => $isSetOwner && ! $set->performed,
            'canMoveSongUp' => $song->position > 1,
            'canMoveSongDown' => $song->position < $songCount,
        ])->render();
    }

    public function slot(Slot $slot, User $viewer): string
    {
        $slot->loadMissing([
            'user',
            'assignments.actor',
            'assignments.target',
            'song.set.session',
        ]);
        $slot->loadCount('attachments');

        $set = $slot->song->set;
        $isSetOwner = $set->owner_id === $viewer->id;
        $canManageSet = $viewer->is_admin || $isSetOwner || $set->isCollaborator($viewer);
        $slotCount = $slot->song->slots()->count();
        $users = User::query()->orderBy('name')->get();
        $assignmentUsers = $this->attendanceService->assignmentUserOptions($set->session, $users, $viewer);
        $notGoingUserIds = $assignmentUsers
            ->filter(fn (array $user) => $user['attendance_status'] === JamSessionAttendance::STATUS_NOT_GOING)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return view('components.sessions.slot-row', [
            'slotModel' => $slot,
            'set' => $set,
            'users' => $users,
            'assignmentUsers' => $assignmentUsers,
            'notGoingUserIds' => $notGoingUserIds,
            'slotOptions' => Slot::options(),
            'currentUserId' => $viewer->id,
            'jamSessionClosed' => (bool) $set->session?->is_closed,
            'isSetOwner' => $isSetOwner,
            'canManageSet' => $canManageSet,
            'canReorderSlots' => $canManageSet && ! $set->performed && ! ($set->session?->is_closed && ! $viewer->is_admin),
            'canMoveSlotUp' => $slot->position > 1,
            'canMoveSlotDown' => $slot->position < $slotCount,
        ])->render();
    }
}
