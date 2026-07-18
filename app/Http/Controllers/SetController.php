<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\Set;
use App\Models\SongRequest;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetController extends Controller
{
    public function summary(Set $set): JsonResponse
    {
        $this->authorize('view', $set->session);

        $set->load([
            'songs.slots.user:id,name',
        ]);

        $checkedInUserIds = JamSessionSignIn::query()
            ->where('jam_session_id', $set->jam_session_id)
            ->pluck('user_id')
            ->all();

        $slotOptions = Slot::options();
        $slotNames = collect(array_keys($slotOptions))
            ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
            ->values();

        $songs = $set->songs->map(function ($song) use ($slotNames, $slotOptions, $checkedInUserIds) {
            $slotMap = [];

            foreach ($slotNames as $slotName) {
                $slot = $song->slots->firstWhere('name', $slotName);

                if (! $slot) {
                    $slotMap[$slotName] = [
                        'state' => 'empty',
                        'display' => '-',
                        'checked_in' => false,
                    ];
                    continue;
                }

                if ($slot->user) {
                    $slotMap[$slotName] = [
                        'state' => 'user',
                        'display' => $slot->user->name,
                        'checked_in' => in_array($slot->user->id, $checkedInUserIds, true),
                    ];
                    continue;
                }

                if (! blank($slot->manual_performer_name)) {
                    $slotMap[$slotName] = [
                        'state' => 'user',
                        'display' => $slot->manual_performer_name,
                        'checked_in' => false,
                    ];
                    continue;
                }

                $slotMap[$slotName] = [
                    'state' => 'open',
                    'display' => 'Open',
                    'checked_in' => false,
                ];
            }

            return [
                'id' => $song->id,
                'artist' => $song->artist,
                'title' => $song->title,
                'slot_map' => $slotMap,
            ];
        })->values();

        return response()->json([
            'slot_names' => $slotNames->map(fn (string $name) => [
                'name' => $name,
                'label' => $slotOptions[$name] ?? ucfirst(str_replace('_', ' ', $name)),
            ])->values(),
            'songs' => $songs,
        ]);
    }

    public function store(Request $request, JamSession $jamSession): RedirectResponse
    {
        $this->authorize('create', Set::class);

        if ($jamSession->is_closed && ! $request->user()->is_admin) {
            return back()->with('status', 'This jam session is closed. No new sets can be created.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $nextPosition = ((int) $jamSession->sets()->max('position')) + 1;

        $jamSession->sets()->create([
            ...$validated,
            'owner_id' => $request->user()->id,
            'position' => $nextPosition,
            'performed' => false,
            'song_requests' => true,
        ]);

        return back()->with('status', 'Set created.');
    }

    public function update(Request $request, Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        $isAdmin = $request->user()->is_admin;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'performed' => ['nullable', 'boolean'],
            'signups_open' => ['nullable', 'boolean'],
            'song_requests' => ['nullable', 'boolean'],
            'jam_session_id' => ['nullable', 'integer', 'exists:jam_sessions,id'],
        ];

        if ($isAdmin) {
            $rules['owner_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['feature_set'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        $wasAcceptingSongRequests = (bool) $set->song_requests;

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? $set->position,
            'performed' => (bool) ($validated['performed'] ?? false),
            'signups_open' => (bool) ($validated['signups_open'] ?? false),
            'song_requests' => (bool) ($validated['song_requests'] ?? false),
            'jam_session_id' => $validated['jam_session_id'] ?? $set->jam_session_id,
        ];

        if ($isAdmin) {
            $updateData['owner_id'] = $validated['owner_id'];
            $updateData['feature_set'] = (bool) ($validated['feature_set'] ?? false);
        }

        $set->update($updateData);

        if ($wasAcceptingSongRequests && ! $updateData['song_requests']) {
            $set->songRequests()
                ->where('status', SongRequest::STATUS_PENDING)
                ->update([
                    'status' => SongRequest::STATUS_REJECTED,
                    'responded_by_user_id' => $request->user()->id,
                    'responded_at' => now(),
                ]);
        }

        return back()->with('status', 'Set updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Set $set): RedirectResponse
    {
        $this->authorize('delete', $set);

        $set->delete();

        return back()->with('status', 'Set removed.');
    }
}
