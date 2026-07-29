<?php

namespace App;

use App\Models\BandTemplate;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;

class SessionCardFragment
{
    public function song(Song $song, User $viewer): string
    {
        $song->loadMissing([
            'slots.user',
            'slots.assignments.actor',
            'slots.assignments.target',
            'set.session',
            'set.owner',
        ]);

        $set = $song->set;
        $isSetOwner = $set->owner_id === $viewer->id;
        $canManageSet = $viewer->is_admin || $isSetOwner || $set->isCollaborator($viewer);
        $songCount = $set->songs()->count();

        return view('components.sessions.song-card', [
            'song' => $song,
            'set' => $set,
            'users' => User::query()->orderBy('name')->get(),
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

        $set = $slot->song->set;
        $isSetOwner = $set->owner_id === $viewer->id;
        $canManageSet = $viewer->is_admin || $isSetOwner || $set->isCollaborator($viewer);
        $slotCount = $slot->song->slots()->count();

        return view('components.sessions.slot-row', [
            'slotModel' => $slot,
            'set' => $set,
            'users' => User::query()->orderBy('name')->get(),
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
