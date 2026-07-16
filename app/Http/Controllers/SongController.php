<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $song = $set->songs()->create([
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
        ]);

        if (! empty($validated['band_template_id'])) {
            $template = BandTemplate::query()
                ->with('slots')
                ->findOrFail($validated['band_template_id']);

            foreach ($template->slots as $templateSlot) {
                $song->slots()->create([
                    'name' => $templateSlot->name,
                ]);
            }
        } elseif (! empty($validated['slot_names'])) {
            foreach (array_unique($validated['slot_names']) as $slotName) {
                $song->slots()->create([
                    'name' => $slotName,
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
        ]);

        $song->update($validated);

        return back()->with('status', 'Song updated.');
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
