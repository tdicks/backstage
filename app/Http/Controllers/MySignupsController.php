<?php

namespace App\Http\Controllers;

use App\Models\SlotAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MySignupsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $signedUpSets = $user->slots()
            ->with(['song.set.session'])
            ->get()
            ->groupBy(fn ($slot) => $slot->song->set->id)
            ->map(function ($slots) {
                $set = $slots->first()->song->set;

                return [
                    'set' => $set,
                    'songs' => $slots
                        ->groupBy('song_id')
                        ->map(function ($songSlots) {
                            return [
                                'song' => $songSlots->first()->song,
                                'slots' => $songSlots->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->sortBy(function ($group) {
                return sprintf(
                    '%d-%010d-%s',
                    $group['set']->performed ? 1 : 0,
                    $group['set']->session->date->timestamp,
                    $group['set']->name
                );
            })
            ->values();

        $slotProposals = SlotAssignment::query()
            ->where('type', SlotAssignment::TYPE_PROPOSAL)
            ->where('target_user_id', $user->id)
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->with(['actor', 'slot.song.set.session'])
            ->orderByDesc('created_at')
            ->get();

        return view('my-signups', [
            'signedUpSets' => $signedUpSets,
            'slotProposals' => $slotProposals,
        ]);
    }
}