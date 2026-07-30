<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotFinderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $slotCoverage = $user->slotCoverageMap();

        $openSlots = Slot::query()
            ->whereNull('user_id')
            ->whereNull('manual_performer_name')
            ->whereHas('song.set', function ($query) use ($user): void {
                $query->visibleTo($user)
                    ->where('signups_open', true);
            })
            ->whereHas('song.set.session', function ($query) use ($user): void {
                $query->visibleTo($user)
                    ->where('is_archived', false)
                    ->whereDate('date', '>=', today());
            })
            ->whereDoesntHave('song.slots', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->with([
                'song.set.session',
                'song.set.owner',
            ])
            ->withCount([
                'assignments as pending_request_count' => function ($query) use ($user): void {
                    $query->where('actor_user_id', $user->id)
                        ->where('type', SlotAssignment::TYPE_REQUEST)
                        ->where('status', SlotAssignment::STATUS_PENDING);
                },
            ])
            ->selectSub(
                SlotAssignment::query()
                    ->select('id')
                    ->whereColumn('slot_id', 'slots.id')
                    ->where('actor_user_id', $user->id)
                    ->where('type', SlotAssignment::TYPE_REQUEST)
                    ->where('status', SlotAssignment::STATUS_PENDING)
                    ->orderByDesc('id')
                    ->limit(1),
                'pending_request_id'
            )
            ->get()
            ->map(function (Slot $slot) use ($slotCoverage): Slot {
                $slot->coverage_state = $slotCoverage[$slot->name] ?? User::SLOT_COVERAGE_UNSPECIFIED;
                $slot->coverage_match = $slot->coverage_state === User::SLOT_COVERAGE_CAN;
                $slot->sort_key = sprintf(
                    '%s|%s|%05d|%05d|%05d|%s|%s',
                    $slot->song->set->session->date->format('Y-m-d'),
                    $slot->coverage_match ? '0' : '1',
                    $slot->song->set->position,
                    $slot->song->position,
                    $slot->position,
                    mb_strtolower($slot->song->artist),
                    mb_strtolower($slot->song->title),
                );

                return $slot;
            })
            ->reject(fn (Slot $slot): bool => $slot->coverage_state === User::SLOT_COVERAGE_WONT)
            ->sortBy('sort_key')
            ->values();

        $sessionGroups = $openSlots
            ->groupBy(fn (Slot $slot) => $slot->song->set->session->id)
            ->map(function ($sessionSlots) {
                $session = $sessionSlots->first()->song->set->session;

                return [
                    'session' => $session,
                    'sets' => $sessionSlots
                        ->groupBy(fn (Slot $slot) => $slot->song->set->id)
                        ->map(function ($setSlots) {
                            $set = $setSlots->first()->song->set;
                            $songGroups = $setSlots
                                ->groupBy(fn (Slot $slot) => $slot->song->id)
                                ->map(function ($songSlots) {
                                    $song = $songSlots->first()->song;

                                    return [
                                        'song' => $song,
                                        'slots' => $songSlots->values(),
                                        'slot_count' => $songSlots->count(),
                                        'coverage_match_count' => $songSlots->where('coverage_match', true)->count(),
                                        'sort_key' => $songSlots->min('sort_key') ?? '',
                                    ];
                                })
                                ->sortBy('sort_key')
                                ->values();

                            return [
                                'set' => $set,
                                'songs' => $songGroups,
                                'slot_count' => $setSlots->count(),
                                'song_count' => $songGroups->count(),
                                'coverage_match_count' => $setSlots->where('coverage_match', true)->count(),
                                'sort_key' => $setSlots->min('sort_key') ?? '',
                            ];
                        })
                        ->sortBy('sort_key')
                        ->values(),
                    'slot_count' => $sessionSlots->count(),
                    'coverage_match_count' => $sessionSlots->where('coverage_match', true)->count(),
                    'sort_key' => $sessionSlots->min('sort_key') ?? '',
                ];
            })
            ->sortBy('sort_key')
            ->values();

        return view('slot-finder.index', [
            'pageName' => 'Find a Slot',
            'openSlotCount' => $openSlots->count(),
            'sessionCount' => $sessionGroups->count(),
            'sessionGroups' => $sessionGroups,
            'slotCoverage' => $slotCoverage,
            'slotCoverageMatchCount' => $openSlots->where('coverage_match', true)->count(),
            'slotOptions' => Slot::options(),
        ]);
    }
}
