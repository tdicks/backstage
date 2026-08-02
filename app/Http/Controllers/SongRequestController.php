<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SlotCompatibility;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SongRequestController extends Controller
{
    public function store(Request $request, Set $set): JsonResponse|RedirectResponse
    {
        if (! $set->song_requests) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This set is not accepting song requests.',
                ], 422);
            }

            return back()->with('status', 'This set is not accepting song requests.');
        }

        if ($set->owner_id === $request->user()->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You can already add songs to your own set.',
                ], 422);
            }

            return back()->with('status', 'You can already add songs to your own set.');
        }

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'jam_standard_song_id' => ['nullable', 'integer', 'exists:jam_standard_songs,id'],
            'slot_names' => ['nullable', 'array'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $requestedSlotNames = collect($validated['slot_names'] ?? [])
            ->map(fn ($slotName) => (string) $slotName)
            ->filter(fn (string $slotName) => in_array($slotName, Slot::keys(), true))
            ->unique()
            ->values()
            ->all();

        $songRequest = $set->songRequests()->create([
            'requester_user_id' => $request->user()->id,
            'jam_standard_song_id' => $validated['jam_standard_song_id'] ?? null,
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'requested_slot_names' => $requestedSlotNames,
            'status' => SongRequest::STATUS_PENDING,
        ]);

        $set->loadMissing('session');

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SONG_REQUEST_RECEIVED,
            app(NotificationService::class)->managersForSet($set),
            $request->user(),
            [
                'title' => 'New song request',
                'body' => $request->user()->name.' requested '.$songRequest->artist.' - '.$songRequest->title.' for '.$set->name.'.',
                'action_url' => route('sessions.show', $set->session).'#set-'.$set->id,
                'action_label' => 'Review request',
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Song request submitted to the set owner.',
                'song_request' => [
                    'id' => $songRequest->id,
                ],
            ], 201);
        }

        return back()->with('status', 'Song request submitted to the set owner.');
    }

    public function respond(Request $request, SongRequest $songRequest): JsonResponse|RedirectResponse
    {
        if ($songRequest->status !== SongRequest::STATUS_PENDING) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This song request has already been processed.',
                ], 422);
            }

            return back()->with('status', 'This song request has already been processed.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id'],
            'approved_slot_names' => ['nullable', 'array'],
            'approved_slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $user = $request->user();
        $songRequest->load(['set.session', 'requester', 'jamStandardSong']);

        $isSetManager = $user->is_admin || $songRequest->set->owner_id === $user->id || $songRequest->set->isCollaborator($user);
        $isRequesterRejectingOwn = $songRequest->requester_user_id === $user->id
            && $validated['status'] === SongRequest::STATUS_REJECTED;

        if (! $isSetManager && ! $isRequesterRejectingOwn) {
            abort(403);
        }

        DB::transaction(function () use ($songRequest, $user, $validated): void {
            $updateData = [
                'status' => $validated['status'],
                'responded_by_user_id' => $user->id,
                'responded_at' => now(),
            ];

            if ($validated['status'] === SongRequest::STATUS_ACCEPTED) {
                $nextSongPosition = ((int) Song::query()
                    ->where('set_id', $songRequest->set_id)
                    ->max('position')) + 1;

                $requestedSlotNames = collect($songRequest->requested_slot_names ?? [])
                    ->map(fn ($slotName) => (string) $slotName)
                    ->filter(fn (string $slotName) => in_array($slotName, Slot::keys(), true))
                    ->unique()
                    ->values();

                $approvedSlotNames = collect($validated['approved_slot_names'] ?? [])
                    ->map(fn ($slotName) => (string) $slotName)
                    ->filter(fn (string $slotName) => in_array($slotName, Slot::keys(), true))
                    ->unique()
                    ->values();

                if ($approvedSlotNames->diff($requestedSlotNames)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'approved_slot_names' => 'Choose only slots from the requester\'s selected "Can cover" options.',
                    ]);
                }

                $song = Song::create([
                    'set_id' => $songRequest->set_id,
                    'jam_standard_song_id' => $songRequest->jam_standard_song_id,
                    'artist' => $songRequest->artist,
                    'title' => $songRequest->title,
                    'notes' => $songRequest->notes,
                    'position' => $nextSongPosition,
                ]);

                $templateId = $songRequest->jam_standard_song_id
                    ? $songRequest->jamStandardSong?->band_template_id
                    : ($validated['band_template_id'] ?? $songRequest->band_template_id);

                if ($templateId) {
                    $template = BandTemplate::query()->with('slots')->findOrFail($templateId);
                    $nextSlotPosition = ((int) $song->slots()->max('position')) + 1;

                    foreach ($template->slots as $templateSlot) {
                        $song->slots()->create([
                            'name' => $templateSlot->name,
                            'position' => $nextSlotPosition++,
                        ]);
                    }
                }

                if ($requestedSlotNames->isNotEmpty()) {
                    $nextSlotPosition = ((int) $song->slots()->max('position')) + 1;

                    foreach ($requestedSlotNames as $requestedSlotName) {
                        $alreadyExists = $song->slots()
                            ->where('name', $requestedSlotName)
                            ->exists();

                        if (! $alreadyExists) {
                            $song->slots()->create([
                                'name' => $requestedSlotName,
                                'position' => $nextSlotPosition++,
                            ]);
                        }
                    }

                    $song->load('slots');

                    if ($approvedSlotNames->isNotEmpty()) {
                        foreach ($approvedSlotNames as $approvedSlotName) {
                            /** @var Slot|null $approvedSlot */
                            $approvedSlot = $song->slots
                                ->where('name', $approvedSlotName)
                                ->first(fn ($slot) => $slot->user_id === null && blank($slot->manual_performer_name));

                            if (! $approvedSlot) {
                                throw ValidationException::withMessages([
                                    'approved_slot_names' => 'One or more selected slots are no longer available for assignment.',
                                ]);
                            }

                            SlotCompatibility::ensureUserCanPerformSlot(
                                $songRequest->requester_user_id,
                                $approvedSlot,
                                $approvedSlotName,
                                'approved_slot_names'
                            );

                            $approvedSlot->update([
                                'user_id' => $songRequest->requester_user_id,
                                'manual_performer_name' => null,
                                'is_claimable_manual' => false,
                            ]);
                        }
                    }

                    if ($songRequest->requester) {
                        $coverageMap = $songRequest->requester->slotCoverageMap();

                        foreach ($requestedSlotNames as $requestedSlotName) {
                            $coverageMap[$requestedSlotName] = User::SLOT_COVERAGE_CAN;
                        }

                        $songRequest->requester->forceFill([
                            'slot_coverage' => $coverageMap,
                        ])->save();
                    }
                }

                $updateData['song_id'] = $song->id;
            }

            $songRequest->update($updateData);
        });

        if ($validated['status'] === SongRequest::STATUS_ACCEPTED) {
            app(NotificationService::class)->notifyUsers(
                NotificationTypeCatalog::SONG_REQUEST_ACCEPTED,
                [$songRequest->requester],
                null,
                [
                    'title' => 'Song request accepted',
                    'body' => $user->name.' added '.$songRequest->artist.' - '.$songRequest->title.' to '.$songRequest->set->name.'.',
                    'action_url' => route('sessions.show', $songRequest->set->session).'#song-'.$songRequest->song_id,
                    'action_label' => 'View song',
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Song request updated.',
            ]);
        }

        return back()->with('status', 'Song request updated.');
    }
}
