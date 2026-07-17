<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(Request $request, Song $song): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot added.',
            ], 201);
        }

        return back()->with('status', 'Slot added.');
    }

    public function update(Request $request, Slot $slot): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot updated.',
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', 'Slot updated.');
    }

    public function take(Request $request, Slot $slot): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot assigned to you.',
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', 'Slot assigned to you.');
    }

    public function release(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        if ($slot->user_id !== $request->user()->id) {
            abort(403);
        }

        $slot->update([
            'user_id' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot released.',
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', 'Slot released.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $slot);

        $slot->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Slot deleted.',
            ]);
        }

        return back()->with('status', 'Slot deleted.');
    }

    private function slotPayload(Slot $slot): array
    {
        $slot->loadMissing('user');

        return [
            'id' => $slot->id,
            'name' => $slot->name,
            'label' => Slot::options()[$slot->name] ?? $slot->name,
            'user_id' => $slot->user_id,
            'user_name' => $slot->user?->name ?? 'Open',
            'is_open' => $slot->isOpen(),
        ];
    }
}
