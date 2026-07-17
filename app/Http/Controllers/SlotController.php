<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(Request $request, Song $song): RedirectResponse
    {
        $this->authorize('update', $song);

        $validated = $request->validate([
            'name' => ['required', 'string', 'in:'.implode(',', Slot::NAMES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $nextPosition = ((int) $song->slots()->max('position')) + 1;

        $song->slots()->create([
            ...$validated,
            'position' => $nextPosition,
        ]);

        return back()->with('status', 'Slot added.');
    }

    public function update(Request $request, Slot $slot): RedirectResponse
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'name' => ['required', 'string', 'in:'.implode(',', Slot::NAMES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $slot->update([
            'name' => $validated['name'],
            'user_id' => $validated['user_id'] ?? null,
            'position' => $validated['position'] ?? $slot->position,
        ]);

        return back()->with('status', 'Slot updated.');
    }

    public function take(Request $request, Slot $slot): RedirectResponse
    {
        $slot->load('song.set');

        if (! $slot->song->set->signups_open) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        if ($slot->song->set->owner_id !== $request->user()->id) {
            abort(403);
        }

        $slot->update([
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Slot assigned to you.');
    }

    public function release(Request $request, Slot $slot): RedirectResponse
    {
        if ($slot->user_id !== $request->user()->id) {
            abort(403);
        }

        $slot->update([
            'user_id' => null,
        ]);

        return back()->with('status', 'Slot released.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot): RedirectResponse
    {
        $this->authorize('delete', $slot);

        $slot->delete();

        return back()->with('status', 'Slot deleted.');
    }
}
