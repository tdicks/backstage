<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamStandardSong;
use App\Models\JamStandardSongRequest;
use App\Models\Slot;
use App\Models\User;
use App\Services\DeezerDurationLookup;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JamStandardSongRequestController extends Controller
{
    public function __construct(private DeezerDurationLookup $deezerDurationLookup) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1', 'required_with:source'],
            'source' => ['nullable', 'string', 'in:deezer', 'required_with:duration'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id', 'required_without:slot_names'],
            'slot_names' => ['nullable', 'array', 'min:1', 'required_without:band_template_id'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
            'requester_slot_names' => ['nullable', 'array'],
            'requester_slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $songSlotNames = $this->slotNames($validated);
        $requesterSlotNames = collect($validated['requester_slot_names'] ?? [])
            ->map(fn ($slotName) => (string) $slotName)
            ->unique()
            ->values();

        if ($requesterSlotNames->diff($songSlotNames)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'requester_slot_names' => 'Choose only slots included in this song request.',
            ]);
        }

        $nearMatches = JamStandardSong::nearMatchesFor($validated['artist'], $validated['title']);

        if (($validated['duration'] ?? null) === null) {
            try {
                $duration = $this->deezerDurationLookup->findDuration($validated['artist'], $validated['title']);
            } catch (\Throwable) {
                $duration = null;
            }

            if ($duration !== null) {
                $validated['duration'] = $duration;
                $validated['source'] = 'deezer';
            }
        }

        $catalogRequest = JamStandardSongRequest::query()->create([
            ...$validated,
            'requester_user_id' => $request->user()->id,
            'slot_names' => $songSlotNames,
            'requester_slot_names' => $requesterSlotNames->all(),
            'status' => JamStandardSongRequest::STATUS_PENDING,
        ]);

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SONG_REQUEST_RECEIVED,
            User::query()->where('is_admin', true)->get(),
            $request->user(),
            [
                'title' => 'Catalog song request received',
                'body' => $request->user()->name.' requested '.$validated['artist'].' - '.$validated['title'].' for Jam Standards.',
                'action_url' => route('jam-standards.index'),
                'action_label' => 'Review catalog requests',
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'request' => $catalogRequest->load('requester:id,name'),
                'near_matches' => $nearMatches->map(fn (JamStandardSong $song) => $song->only(['artist', 'title']))->all(),
            ], 201);
        }

        $redirect = to_route('jam-standards.index')->with('status', 'Catalog song request sent for review.');

        if ($nearMatches->isNotEmpty()) {
            return $redirect->with('warning', 'A similar catalog song may already exist: '.$nearMatches->first()->artist.' - '.$nearMatches->first()->title.'.');
        }

        return $redirect;
    }

    public function respond(Request $request, JamStandardSongRequest $jamStandardSongRequest): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        abort_if($jamStandardSongRequest->status !== JamStandardSongRequest::STATUS_PENDING, 422);

        $catalogSong = DB::transaction(function () use ($jamStandardSongRequest, $validated, $request): ?JamStandardSong {
            $jamStandardSongRequest->forceFill([
                'status' => $validated['status'],
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            if ($validated['status'] !== JamStandardSongRequest::STATUS_APPROVED) {
                return null;
            }

            $catalogSong = JamStandardSong::query()->create([
                'artist' => $jamStandardSongRequest->artist,
                'title' => $jamStandardSongRequest->title,
                'notes' => $jamStandardSongRequest->notes,
                'duration' => $jamStandardSongRequest->duration,
                'source' => $jamStandardSongRequest->source,
                'band_template_id' => $jamStandardSongRequest->band_template_id,
                'is_active' => true,
                'created_by_user_id' => $jamStandardSongRequest->requester_user_id,
            ]);

            collect($jamStandardSongRequest->slot_names ?? [])->each(fn (string $slotName, int $position) => $catalogSong->slots()->create([
                'name' => $slotName,
                'position' => $position + 1,
            ]));

            collect($jamStandardSongRequest->requester_slot_names ?? [])->each(fn (string $slotName) => $catalogSong->userSlots()->create([
                'user_id' => $jamStandardSongRequest->requester_user_id,
                'slot_name' => $slotName,
            ]));

            return $catalogSong;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'request_id' => $jamStandardSongRequest->id,
                'status' => $validated['status'],
                'song' => $catalogSong?->load('slots'),
                'remaining_request_count' => JamStandardSongRequest::query()
                    ->where('status', JamStandardSongRequest::STATUS_PENDING)
                    ->count(),
            ]);
        }

        return to_route('jam-standards.index')->with('status', 'Catalog request '.$validated['status'].'.');
    }

    public function destroy(Request $request, JamStandardSongRequest $jamStandardSongRequest): RedirectResponse|JsonResponse
    {
        abort_unless($jamStandardSongRequest->requester_user_id === $request->user()->id, 403);
        abort_if($jamStandardSongRequest->status !== JamStandardSongRequest::STATUS_PENDING, 422);

        $requestId = $jamStandardSongRequest->id;
        $jamStandardSongRequest->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'request_id' => $requestId,
                'status' => 'cancelled',
                'remaining_request_count' => JamStandardSongRequest::query()
                    ->where('status', JamStandardSongRequest::STATUS_PENDING)
                    ->count(),
            ]);
        }

        return to_route('jam-standards.index')->with('status', 'Catalog request cancelled.');
    }

    /** @param array<string, mixed> $validated */
    private function slotNames(array $validated): array
    {
        $templateSlots = empty($validated['band_template_id'])
            ? collect()
            : BandTemplate::query()->findOrFail($validated['band_template_id'])->slots->pluck('name');

        return collect($validated['slot_names'] ?? [])
            ->merge($templateSlots)
            ->filter(fn ($slotName) => in_array($slotName, Slot::keys(), true))
            ->unique()
            ->values()
            ->all();
    }
}
