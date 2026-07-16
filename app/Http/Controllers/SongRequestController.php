<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Song;
use App\Models\SongRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SongRequestController extends Controller
{
    public function store(Request $request, Set $set): RedirectResponse
    {
        if ($set->owner_id === $request->user()->id) {
            return back()->with('status', 'You can already add songs to your own set.');
        }

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $set->songRequests()->create([
            'requester_user_id' => $request->user()->id,
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'status' => SongRequest::STATUS_PENDING,
        ]);

        return back()->with('status', 'Song request submitted to the set owner.');
    }

    public function respond(Request $request, SongRequest $songRequest): RedirectResponse
    {
        if ($songRequest->status !== SongRequest::STATUS_PENDING) {
            return back()->with('status', 'This song request has already been processed.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id'],
        ]);

        $user = $request->user();
        $songRequest->load('set');

        if (! $user->is_admin && $songRequest->set->owner_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($songRequest, $user, $validated): void {
            $updateData = [
                'status' => $validated['status'],
                'responded_by_user_id' => $user->id,
                'responded_at' => now(),
            ];

            if ($validated['status'] === SongRequest::STATUS_ACCEPTED) {
                $song = Song::create([
                    'set_id' => $songRequest->set_id,
                    'artist' => $songRequest->artist,
                    'title' => $songRequest->title,
                    'notes' => $songRequest->notes,
                ]);

                $templateId = $validated['band_template_id'] ?? $songRequest->band_template_id;

                if ($templateId) {
                    $template = BandTemplate::query()->with('slots')->findOrFail($templateId);

                    foreach ($template->slots as $templateSlot) {
                        $song->slots()->create([
                            'name' => $templateSlot->name,
                        ]);
                    }
                }

                $updateData['song_id'] = $song->id;
            }

            $songRequest->update($updateData);
        });

        return back()->with('status', 'Song request updated.');
    }
}