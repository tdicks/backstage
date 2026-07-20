<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SongController extends Controller
{
    public function store(Request $request, Set $set): JsonResponse|RedirectResponse
    {
        $this->authorize('create', [Song::class, $set]);

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id'],
            'slot_names' => ['nullable', 'array'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $nextSongPosition = ((int) $set->songs()->max('position')) + 1;

        $song = $set->songs()->create([
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'source' => $validated['source'] ?? null,
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

        $set->loadMissing('session', 'songs.slots');

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SET_UPDATED,
            app(NotificationService::class)->involvedUsersForSet($set),
            $request->user(),
            [
                'title' => 'Set updated',
                'body' => $request->user()->name.' added '.$song->artist.' - '.$song->title.' to '.$set->name.'.',
                'action_url' => route('sessions.show', $set->session).'#song-'.$song->id,
                'action_label' => 'Open set',
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Song added to set.',
                'song' => [
                    'id' => $song->id,
                    'artist' => $song->artist,
                    'title' => $song->title,
                ],
            ]);
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

        if ($set->performed) {
            abort(403, 'Cannot reorder songs in a performed set.');
        }

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
        $song->loadMissing('set.session', 'set.songs.slots');
        $set = $song->set;
        $title = $song->artist.' - '.$song->title;

        $song->delete();

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SET_UPDATED,
            app(NotificationService::class)->involvedUsersForSet($set),
            request()->user(),
            [
                'title' => 'Set updated',
                'body' => request()->user()->name.' removed '.$title.' from '.$set->name.'.',
                'action_url' => route('sessions.show', $set->session).'#set-'.$set->id,
                'action_label' => 'Open set',
            ]
        );

        return back()->with('status', 'Song removed.');
    }
}
