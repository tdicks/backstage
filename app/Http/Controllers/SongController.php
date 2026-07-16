<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SongController extends Controller
{
    public function store(Request $request, Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id'],
            'slot_names' => ['nullable', 'array'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::NAMES)],
        ]);

        $nextSongPosition = ((int) $set->songs()->max('position')) + 1;

        $song = $set->songs()->create([
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'position' => $nextSongPosition,
        ]);

        $nextSlotPosition = ((int) $song->slots()->max('position')) + 1;

        if (! empty($validated['band_template_id'])) {
            $template = BandTemplate::query()
                ->with('slots')
                ->findOrFail($validated['band_template_id']);

            foreach ($template->slots as $templateSlot) {
                $song->slots()->create([
                    'name' => $templateSlot->name,
                    'position' => $nextSlotPosition++,
                ]);
            }
        } elseif (! empty($validated['slot_names'])) {
            foreach (array_unique($validated['slot_names']) as $slotName) {
                $song->slots()->create([
                    'name' => $slotName,
                    'position' => $nextSlotPosition++,
                ]);
            }
        }

        return back()->with('status', 'Song added to set.');
    }

    public function update(Request $request, Song $song): RedirectResponse
    {
        $this->authorize('update', $song);

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $song->update([
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'position' => $validated['position'] ?? $song->position,
        ]);

        return back()->with('status', 'Song updated.');
    }

    public function reorder(Request $request, Set $set)
    {
        $this->authorize('update', $set);

        $validated = $request->validate([
            'song_ids' => ['required', 'array', 'min:1'],
            'song_ids.*' => ['integer'],
        ]);

        $orderedSongIds = array_values(array_map('intval', $validated['song_ids']));
        $uniqueOrderedSongIds = array_values(array_unique($orderedSongIds));

        if (count($orderedSongIds) !== count($uniqueOrderedSongIds)) {
            abort(422, 'Song order contains duplicates.');
        }

        $setSongIds = Song::query()
            ->where('set_id', $set->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($uniqueOrderedSongIds);
        $sortedSetSongIds = $setSongIds;
        sort($sortedSetSongIds);

        if ($uniqueOrderedSongIds !== $sortedSetSongIds) {
            abort(422, 'Invalid song list for this set.');
        }

        DB::transaction(function () use ($orderedSongIds, $set): void {
            foreach (array_values($orderedSongIds) as $index => $songId) {
                Song::query()
                    ->where('set_id', $set->id)
                    ->where('id', $songId)
                    ->update(['position' => $index + 1]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Song order updated.',
            ]);
        }

        return back()->with('status', 'Song order updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Song $song): RedirectResponse
    {
        $this->authorize('delete', $song);

        $song->delete();

        return back()->with('status', 'Song removed.');
    }
}
