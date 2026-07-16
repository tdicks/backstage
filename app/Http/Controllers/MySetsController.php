<?php

namespace App\Http\Controllers;

use App\Models\Set;
use App\Models\SlotAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MySetsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $ownedSets = Set::query()
            ->where('owner_id', $user->id)
            ->with([
                'session',
                'songs.slots.user',
            ])
            ->get()
            ->sortBy(function (Set $set) {
                return sprintf(
                    '%d-%010d-%s',
                    $set->performed ? 1 : 0,
                    $set->session->date->timestamp,
                    $set->name
                );
            })
            ->values();

        $pendingSlotApprovals = SlotAssignment::query()
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot.song.set', function ($query) use ($user) {
                $query->where('owner_id', $user->id);
            })
            ->with([
                'actor',
                'target',
                'slot.song.set.session',
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('my-sets', [
            'ownedSets' => $ownedSets,
            'pendingSlotApprovals' => $pendingSlotApprovals,
        ]);
    }
}
