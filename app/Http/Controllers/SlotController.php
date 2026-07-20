<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Services\SlotCompatibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    public function store(Request $request, Song $song): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $song);

        $validated = $request->validate([
            'name' => ['required', 'string', 'in:'.implode(',', Slot::keys())],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($validated['user_id'])) {
            SlotCompatibility::ensureUserCanPerformSlotInSong((int) $validated['user_id'], $song, $validated['name']);
        }

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
            'name' => ['required', 'string', 'in:'.implode(',', Slot::keys())],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'manual_performer_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($validated['user_id'])) {
            SlotCompatibility::ensureUserCanPerformSlot((int) $validated['user_id'], $slot, $validated['name']);
        }

        $manualPerformerName = trim((string) ($validated['manual_performer_name'] ?? ''));
        if (! empty($validated['user_id'])) {
            $manualPerformerName = '';
        }

        DB::transaction(function () use ($slot, $validated, $manualPerformerName): void {
            $slot->update([
                'name' => $validated['name'],
                'user_id' => $validated['user_id'] ?? null,
                'manual_performer_name' => $manualPerformerName !== '' ? $manualPerformerName : null,
                'position' => $validated['position'] ?? $slot->position,
            ]);

            if (! empty($validated['user_id'])) {
                SlotAssignment::query()
                    ->where('slot_id', $slot->id)
                    ->where('target_user_id', $validated['user_id'])
                    ->whereIn('status', [
                        SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
                        SlotAssignment::STATUS_PENDING,
                    ])
                    ->update([
                        'status' => SlotAssignment::STATUS_ACCEPTED,
                        'responded_at' => now(),
                    ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot updated.',
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', 'Slot updated.');
    }

    public function reorder(Request $request, Song $song): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $song);

        if ($song->set->performed) {
            abort(403, 'Cannot reorder slots in a performed set.');
        }

        $validated = $request->validate([
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer'],
        ]);

        $orderedSlotIds = array_values(array_map('intval', $validated['slot_ids']));
        $uniqueOrderedSlotIds = array_values(array_unique($orderedSlotIds));

        if (count($orderedSlotIds) !== count($uniqueOrderedSlotIds)) {
            abort(422, 'Slot order contains duplicates.');
        }

        $songSlotIds = $song->slots()->pluck('id')->map(fn ($id) => (int) $id)->all();

        sort($uniqueOrderedSlotIds);
        $sortedSongSlotIds = $songSlotIds;
        sort($sortedSongSlotIds);

        if ($uniqueOrderedSlotIds !== $sortedSongSlotIds) {
            abort(422, 'Invalid slot list for this song.');
        }

        DB::transaction(function () use ($orderedSlotIds, $song): void {
            foreach (array_values($orderedSlotIds) as $index => $slotId) {
                Slot::query()
                    ->where('song_id', $song->id)
                    ->where('id', $slotId)
                    ->update(['position' => $index + 1]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot order updated.',
            ]);
        }

        return back()->with('status', 'Slot order updated.');
    }

    public function take(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        $slot->load('song.set');

        if (! $slot->song->set->signups_open) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        $set = $slot->song->set;
        $user = $request->user();

        if ($set->owner_id !== $user->id && ! $set->isCollaborator($user) && ! $user->is_admin) {
            abort(403);
        }

        try {
            SlotCompatibility::ensureUserCanPerformSlot($request->user()->id, $slot);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                $errors = $exception->errors();
                $message = collect($errors)->flatten()->first() ?? 'This slot conflicts with another slot on this song.';

                return response()->json([
                    'message' => $message,
                    'errors' => $errors,
                ], 422);
            }

            throw $exception;
        }

        $slot->update([
            'user_id' => $request->user()->id,
            'manual_performer_name' => null,
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
            'manual_performer_name' => null,
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
            'user_name' => $slot->assignedPerformerName(),
            'is_open' => $slot->isOpen(),
        ];
    }
}
