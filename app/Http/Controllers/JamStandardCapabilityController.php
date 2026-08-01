<?php

namespace App\Http\Controllers;

use App\Models\JamStandardSong;
use App\Models\JamStandardUserSlot;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JamStandardCapabilityController extends Controller
{
    public function update(Request $request, JamStandardSong $jamStandardSong): JsonResponse
    {
        $validated = $request->validate([
            'slot_names' => ['nullable', 'array'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $slotNames = collect($validated['slot_names'] ?? [])
            ->intersect($jamStandardSong->slots()->pluck('name'))
            ->unique()
            ->values();

        $jamStandardSong->userSlots()->where('user_id', $request->user()->id)->delete();

        $slotNames->each(fn (string $slotName) => $jamStandardSong->userSlots()->create([
            'user_id' => $request->user()->id,
            'slot_name' => $slotName,
        ]));

        $recentCapabilityCounts = JamStandardUserSlot::recentCapabilityCountsForSongs([$jamStandardSong->id])[$jamStandardSong->id] ?? [];
        $slotCapabilityCounts = $jamStandardSong->slots()
            ->pluck('name')
            ->mapWithKeys(fn (string $slotName) => [$slotName => max(0, (int) ($recentCapabilityCounts[$slotName] ?? 0))])
            ->all();

        return response()->json([
            'slot_names' => $slotNames->all(),
            'slot_capability_counts' => $slotCapabilityCounts,
        ]);
    }
}
