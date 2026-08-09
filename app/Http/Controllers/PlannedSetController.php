<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\JamStandardSong;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\SlotType;
use App\Models\Song;
use App\Models\SongRequest;
use App\Models\User;
use App\Services\DeezerArtworkLookupService;
use App\Services\SlotCompatibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlannedSetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $attendanceSessions = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'is_closed']);

        $scheduleSessionOptions = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'is_closed']);

        $sets = Set::query()
            ->draft()
            ->visibleTo($user)
            ->withCount('attachments')
            ->with([
                'owner:id,name',
                'songs.slots.user',
                'songs.slots.assignments.actor:id,name',
                'songs.slots.assignments.target:id,name',
                'songRequests' => fn ($query) => $query
                    ->where('status', SongRequest::STATUS_PENDING)
                    ->with(['requester:id,name', 'bandTemplate:id,name', 'jamStandardSong:id,band_template_id'])
                    ->latest('id'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $participantUserIds = $sets
            ->flatMap(fn (Set $set): array => $this->participantUserIdsForSet($set))
            ->unique()
            ->values();

        $collaboratorNames = User::query()
            ->whereIn('id', $participantUserIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();

        $attendanceMap = JamSessionAttendance::query()
            ->whereIn('jam_session_id', $attendanceSessions->pluck('id'))
            ->whereIn('user_id', $participantUserIds)
            ->get(['jam_session_id', 'user_id', 'status'])
            ->groupBy('jam_session_id')
            ->map(fn ($records) => $records
                ->pluck('status', 'user_id')
                ->mapWithKeys(fn (string $status, int|string $participantId): array => [(int) $participantId => $status])
                ->all())
            ->all();

        $initialSets = $sets
            ->map(fn (Set $set): array => $this->toSetPayload($set, $user, $collaboratorNames, $attendanceSessions, $attendanceMap))
            ->values();

        $attendanceSessionOptions = $attendanceSessions
            ->map(fn (JamSession $session): array => [
                'id' => $session->id,
                'name' => $session->name,
                'date_label' => $session->date->format('D, M j, Y'),
                'is_closed' => (bool) $session->is_closed,
            ])
            ->values();

        $scheduleOptions = $scheduleSessionOptions
            ->map(fn (JamSession $session): array => [
                'id' => $session->id,
                'name' => $session->name,
                'date_label' => $session->date->format('D, M j, Y'),
                'is_closed' => (bool) $session->is_closed,
            ])
            ->values();

        $collaboratorOptions = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
            ])
            ->values();

        $templateOptions = BandTemplate::query()
            ->with('slots')
            ->orderBy('name')
            ->get()
            ->map(fn (BandTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'slot_names' => $template->slots
                    ->pluck('name')
                    ->map(fn (string $name): string => $name)
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values();

        $jamStandardSongs = JamStandardSong::query()
            ->orderBy('artist')
            ->orderBy('title')
            ->get(['id', 'artist', 'title'])
            ->map(fn (JamStandardSong $song): array => [
                'id' => $song->id,
                'artist' => $song->artist,
                'title' => $song->title,
            ])
            ->values();

        return view('sets.planned.index', [
            'initialPlannedSets' => $initialSets,
            'attendanceSessionOptions' => $attendanceSessionOptions,
            'scheduleSessionOptions' => $scheduleOptions,
            'collaboratorOptions' => $collaboratorOptions,
            'templateOptions' => $templateOptions,
            'jamStandardSongs' => $jamStandardSongs,
            'slotOptions' => Slot::options(),
            'slotConflicts' => $this->slotConflicts(),
        ]);
    }

    public function respondSongRequest(Request $request, Set $set, SongRequest $songRequest): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        if ((int) $songRequest->set_id !== (int) $set->id) {
            abort(404);
        }

        if ($songRequest->status !== SongRequest::STATUS_PENDING) {
            return response()->json([
                'message' => 'This song request has already been processed.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id'],
            'approved_slot_names' => ['nullable', 'array'],
            'approved_slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $user = $request->user();
        $songRequest->loadMissing(['requester', 'jamStandardSong:id,band_template_id']);

        $isSetManager = $this->isPlannedSetManager($user, $set);
        $isRequesterRejectingOwn = (int) $songRequest->requester_user_id === (int) $user->id
            && $validated['status'] === SongRequest::STATUS_REJECTED;

        if (! $isSetManager && ! $isRequesterRejectingOwn) {
            abort(403);
        }

        $createdSong = null;

        DB::transaction(function () use ($songRequest, $user, $validated, &$createdSong): void {
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
                $createdSong = $song;
            }

            $songRequest->update($updateData);
        });

        return response()->json([
            'message' => $validated['status'] === SongRequest::STATUS_ACCEPTED
                ? 'Song request approved.'
                : 'Song request rejected.',
            'song_request_id' => $songRequest->id,
            'song' => $createdSong
                ? $this->toSongPayload($createdSong->fresh('slots.user', 'slots.assignments'), $request->user())
                : null,
        ]);
    }

    public function respondSlotAssignment(Request $request, Set $set, SlotAssignment $slotAssignment): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slotAssignment->loadMissing('slot.song.set', 'target', 'actor');
        if ((int) $slotAssignment->slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        if ($slotAssignment->status !== SlotAssignment::STATUS_PENDING) {
            return response()->json([
                'message' => 'This slot request has already been processed.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        $user = $request->user();
        $isSetManager = $this->isPlannedSetManager($user, $set);
        $isActorRejectingOwn = (int) $slotAssignment->actor_user_id === (int) $user->id
            && $validated['status'] === SlotAssignment::STATUS_REJECTED;

        if (! $isSetManager && ! $isActorRejectingOwn) {
            abort(403);
        }

        if ($validated['status'] === SlotAssignment::STATUS_ACCEPTED && ! $isSetManager) {
            abort(403);
        }

        if ($validated['status'] === SlotAssignment::STATUS_ACCEPTED) {
            try {
                SlotCompatibility::ensureUserCanPerformSlot($slotAssignment->target_user_id, $slotAssignment->slot);
            } catch (ValidationException $exception) {
                $errors = $exception->errors();
                $message = collect($errors)->flatten()->first() ?? 'This request conflicts with another slot on this song.';

                return response()->json([
                    'message' => $message,
                    'errors' => $errors,
                ], 422);
            }
        }

        $processedIds = [$slotAssignment->id];

        DB::transaction(function () use ($slotAssignment, $validated, &$processedIds): void {
            $slotAssignment->update([
                'status' => $validated['status'],
                'responded_at' => now(),
            ]);

            if ($validated['status'] === SlotAssignment::STATUS_ACCEPTED) {
                $processedIds = array_values(array_unique([
                    ...$processedIds,
                    ...$this->assignSlotAndReleaseConflictsForPlanned($slotAssignment),
                ]));
            }
        });

        return response()->json([
            'message' => $validated['status'] === SlotAssignment::STATUS_ACCEPTED
                ? 'Slot request approved.'
                : 'Slot request rejected.',
            'slot_assignment_id' => $slotAssignment->id,
            'processed_slot_assignment_ids' => $processedIds,
            'song' => $this->toSongPayload($slotAssignment->slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Set::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_hidden' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
            'song_requests' => ['nullable', 'boolean'],
            'signups_open' => ['nullable', 'boolean'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))],
            'candidate_session_ids' => ['nullable', 'array'],
            'candidate_session_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $collaboratorIds = $this->normalizedCollaboratorIds($validated['collaborator_ids'] ?? [], $user->id);
        $candidateSessionIds = $this->normalizedCandidateSessionIds($validated['candidate_session_ids'] ?? [], $user);

        $set = Set::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $user->id,
            'jam_session_id' => null,
            'lifecycle_state' => Set::LIFECYCLE_DRAFT,
            'position' => 0,
            'performed' => false,
            'signups_open' => (bool) ($validated['signups_open'] ?? false),
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'song_requests' => (bool) ($validated['song_requests'] ?? true),
            'feature_set' => false,
            'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            'collaborator_ids' => $collaboratorIds !== [] ? $collaboratorIds : null,
            'candidate_session_ids' => $candidateSessionIds !== [] ? $candidateSessionIds : null,
        ]);

        $set->load('owner:id,name');

        $collaboratorNames = User::query()
            ->whereIn('id', $set->collaboratorUserIds())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();

        $attendanceSessions = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'is_closed']);

        $attendanceMap = JamSessionAttendance::query()
            ->whereIn('jam_session_id', $attendanceSessions->pluck('id'))
            ->whereIn('user_id', $this->participantUserIdsForSet($set))
            ->get(['jam_session_id', 'user_id', 'status'])
            ->groupBy('jam_session_id')
            ->map(fn ($records) => $records
                ->pluck('status', 'user_id')
                ->mapWithKeys(fn (string $status, int|string $participantId): array => [(int) $participantId => $status])
                ->all())
            ->all();

        return response()->json([
            'message' => 'Planned set created.',
            'set' => $this->toSetPayload($set, $user, $collaboratorNames, $attendanceSessions, $attendanceMap),
        ], 201);
    }

    public function update(Request $request, Set $set): JsonResponse
    {
        $this->authorize('managePlanned', $set);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_hidden' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
            'song_requests' => ['nullable', 'boolean'],
            'signups_open' => ['nullable', 'boolean'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))],
            'candidate_session_ids' => ['nullable', 'array'],
            'candidate_session_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $collaboratorIds = $this->normalizedCollaboratorIds($validated['collaborator_ids'] ?? [], $set->owner_id);
        $candidateSessionIds = $this->normalizedCandidateSessionIds($validated['candidate_session_ids'] ?? [], $user);

        $set->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            'song_requests' => (bool) ($validated['song_requests'] ?? false),
            'signups_open' => (bool) ($validated['signups_open'] ?? false),
            'collaborator_ids' => $collaboratorIds !== [] ? $collaboratorIds : null,
            'candidate_session_ids' => $candidateSessionIds !== [] ? $candidateSessionIds : null,
        ]);

        $set->load('owner:id,name');

        $collaboratorNames = User::query()
            ->whereIn('id', $set->collaboratorUserIds())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();

        $attendanceSessions = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'is_closed']);

        $attendanceMap = JamSessionAttendance::query()
            ->whereIn('jam_session_id', $attendanceSessions->pluck('id'))
            ->whereIn('user_id', $this->participantUserIdsForSet($set))
            ->get(['jam_session_id', 'user_id', 'status'])
            ->groupBy('jam_session_id')
            ->map(fn ($records) => $records
                ->pluck('status', 'user_id')
                ->mapWithKeys(fn (string $status, int|string $participantId): array => [(int) $participantId => $status])
                ->all())
            ->all();

        return response()->json([
            'message' => 'Planned set updated.',
            'set' => $this->toSetPayload($set, $user, $collaboratorNames, $attendanceSessions, $attendanceMap),
        ]);
    }

    public function schedule(Request $request, Set $set): JsonResponse
    {
        $this->authorize('schedule', $set);

        $validated = $request->validate([
            'jam_session_id' => ['required', 'integer', 'exists:jam_sessions,id'],
            'not_going_slot_action' => ['nullable', 'in:release_slots,keep_claimable'],
        ]);

        $user = $request->user();
        $jamSession = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->find((int) $validated['jam_session_id']);

        $candidateSessionIds = collect($set->candidate_session_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($candidateSessionIds === []) {
            return response()->json([
                'message' => 'Add at least one candidate jam session before scheduling this set.',
            ], 422);
        }

        if (! in_array((int) $validated['jam_session_id'], $candidateSessionIds, true)) {
            return response()->json([
                'message' => 'Choose one of this set\'s candidate jam sessions.',
            ], 422);
        }

        if (! $jamSession) {
            return response()->json([
                'message' => 'Choose a visible, active jam session.',
            ], 422);
        }

        if (! $user->is_admin && $jamSession->is_closed) {
            return response()->json([
                'message' => 'Sets can only be scheduled to open jam sessions.',
            ], 422);
        }

        if (! $user->is_admin && $jamSession->date->isBefore(today())) {
            return response()->json([
                'message' => 'Sets can only be scheduled to today or future jam sessions.',
            ], 422);
        }

        $participantIds = $this->participantUserIdsForSet($set);
        $sessionStatuses = JamSessionAttendance::query()
            ->where('jam_session_id', $jamSession->id)
            ->whereIn('user_id', $participantIds)
            ->pluck('status', 'user_id')
            ->mapWithKeys(fn (string $status, int|string $participantId): array => [(int) $participantId => $status])
            ->all();

        $notGoingParticipantIds = collect($participantIds)
            ->filter(fn (int $participantId): bool => ($sessionStatuses[$participantId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_NOT_GOING)
            ->values()
            ->all();

        $notGoingSlotAction = $validated['not_going_slot_action'] ?? null;
        if ($notGoingParticipantIds !== [] && ! in_array($notGoingSlotAction, ['release_slots', 'keep_claimable'], true)) {
            return response()->json([
                'message' => 'Choose how to handle slots for participants marked Not Going.',
                'errors' => [
                    'not_going_slot_action' => ['Choose how to handle slots for participants marked Not Going.'],
                ],
            ], 422);
        }

        $nextPosition = ((int) $jamSession->sets()->max('position')) + 1;

        DB::transaction(function () use ($set, $jamSession, $nextPosition, $notGoingParticipantIds, $notGoingSlotAction): void {
            $set->update([
                'jam_session_id' => $jamSession->id,
                'lifecycle_state' => Set::LIFECYCLE_SCHEDULED,
                'position' => $nextPosition,
            ]);

            if ($notGoingParticipantIds === []) {
                return;
            }

            $songIds = $set->songs()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($songIds === []) {
                return;
            }

            if ($notGoingSlotAction === 'release_slots') {
                Slot::query()
                    ->whereIn('song_id', $songIds)
                    ->whereIn('user_id', $notGoingParticipantIds)
                    ->update([
                        'user_id' => null,
                        'manual_performer_name' => null,
                        'is_claimable_manual' => false,
                    ]);

                return;
            }

            if ($notGoingSlotAction === 'keep_claimable') {
                Slot::query()
                    ->whereIn('song_id', $songIds)
                    ->whereIn('user_id', $notGoingParticipantIds)
                    ->update([
                        'is_claimable_manual' => true,
                    ]);
            }
        });

        return response()->json([
            'message' => 'Set scheduled.',
            'set_id' => $set->id,
            'session_url' => route('sessions.show', $jamSession).'#set-'.$set->id,
        ]);
    }

    public function addSong(Request $request, Set $set, DeezerArtworkLookupService $artworkLookupService): JsonResponse
    {
        $this->authorize('managePlanned', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

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

        $artworkLookupService->forgetArtworkTilesForSet($set);

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

            foreach ($template->slots->pluck('name')->unique() as $slotName) {
                $song->slots()->create([
                    'name' => (string) $slotName,
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

        $song->load('slots');

        return response()->json([
            'message' => 'Song added to set.',
            'song' => $this->toSongPayload($song),
        ], 201);
    }

    public function updateSong(Request $request, Set $set, Song $song): JsonResponse
    {
        $this->authorize('managePlanned', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        if ((int) $song->set_id !== (int) $set->id) {
            abort(404);
        }

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1', 'required_with:source'],
            'source' => ['nullable', 'string', 'in:deezer', 'required_with:duration'],
        ]);

        $artworkLookupService->forgetArtworkTilesForSet($set);

        $song->update([
            'artist' => $validated['artist'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'source' => $validated['source'] ?? null,
        ]);

        return response()->json([
            'message' => 'Song updated.',
            'song' => $this->toSongPayload($song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    public function addSlot(Request $request, Set $set, Song $song): JsonResponse
    {
        $this->authorize('managePlanned', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        if ((int) $song->set_id !== (int) $set->id) {
            abort(404);
        }

        $validated = $request->validate([
            'addition_mode' => ['nullable', 'string', 'in:individual,template'],
            'name' => ['nullable', 'string', 'in:'.implode(',', Slot::keys()), 'required_unless:addition_mode,template', 'prohibited_if:addition_mode,template'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id', 'required_if:addition_mode,template', 'prohibited_unless:addition_mode,template'],
        ]);

        $additionMode = $validated['addition_mode'] ?? 'individual';
        $createdSlotIds = [];

        if ($additionMode === 'template') {
            $template = BandTemplate::query()
                ->with('slots')
                ->findOrFail($validated['band_template_id']);
            $existingSlotNames = $song->slots()->pluck('name')->all();
            $slotNames = $template->slots
                ->pluck('name')
                ->unique()
                ->reject(fn (string $slotName) => in_array($slotName, $existingSlotNames, true));
            $nextPosition = ((int) $song->slots()->max('position')) + 1;

            foreach ($slotNames as $slotName) {
                $slot = $song->slots()->create([
                    'name' => $slotName,
                    'position' => $nextPosition++,
                ]);
                $createdSlotIds[] = $slot->id;
            }

            $song->load('slots');

            return response()->json([
                'message' => 'Band template applied.',
                'song' => $this->toSongPayload($song),
                'created_slot_ids' => $createdSlotIds,
            ], 201);
        }

        $nextPosition = ((int) $song->slots()->max('position')) + 1;

        $slot = $song->slots()->create([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'position' => $nextPosition,
        ]);

        $song->load('slots');

        return response()->json([
            'message' => 'Slot added.',
            'slot' => $this->toSlotPayload($slot, null, $request->user()),
            'song' => $this->toSongPayload($song, $request->user()),
        ], 201);
    }

    public function takeSlot(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        $isSetManager = $this->isPlannedSetManager($request->user(), $set);
        $slotIsClaimable = (bool) $slot->is_claimable_manual;
        $canFreeTake = $set->free_for_all && ($slot->isOpen() || $slotIsClaimable);

        if (! $set->signups_open && ! $isSetManager) {
            return response()->json([
                'message' => 'Sign ups are closed for this set.',
            ], 422);
        }

        if ((int) $slot->user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'You already have this slot.',
                'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
            ]);
        }

        if (! $slot->isOpen() && ! (bool) $slot->is_claimable_manual) {
            return response()->json([
                'message' => 'This slot is already assigned. Request it instead.',
            ], 422);
        }

        if (! $isSetManager && ! $canFreeTake) {
            return response()->json([
                'message' => 'This slot is not available for direct claiming on this set.',
            ], 403);
        }

        try {
            SlotCompatibility::ensureUserCanPerformSlot($request->user()->id, $slot);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first() ?? 'This slot conflicts with another slot on this song.';

            return response()->json([
                'message' => $message,
                'errors' => $errors,
            ], 422);
        }

        DB::transaction(function () use ($request, $slot): void {
            $slot->update([
                'user_id' => $request->user()->id,
                'manual_performer_name' => null,
                'is_claimable_manual' => false,
            ]);

            SlotAssignment::query()
                ->where('slot_id', $slot->id)
                ->where('actor_user_id', $request->user()->id)
                ->where('target_user_id', $request->user()->id)
                ->where('type', SlotAssignment::TYPE_REQUEST)
                ->where('status', SlotAssignment::STATUS_PENDING)
                ->update([
                    'status' => SlotAssignment::STATUS_ACCEPTED,
                    'responded_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'Slot assigned to you.',
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    public function requestSlot(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        $isSetManager = $this->isPlannedSetManager($request->user(), $set);
        $slotIsClaimable = (bool) $slot->is_claimable_manual;

        if (! $set->signups_open) {
            return response()->json([
                'message' => 'Sign ups are closed for this set.',
            ], 422);
        }

        if (! $isSetManager) {
            if ($set->free_for_all) {
                return response()->json([
                    'message' => 'This set is free for all. You can claim available slots directly.',
                ], 422);
            }

            if (! $slot->isOpen() && ! $slotIsClaimable) {
                return response()->json([
                    'message' => 'This slot cannot be requested right now.',
                ], 422);
            }
        }

        if ((int) $slot->user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'You already have this slot.',
            ], 422);
        }

        $existingPending = SlotAssignment::query()
            ->where('slot_id', $slot->id)
            ->where('actor_user_id', $request->user()->id)
            ->where('target_user_id', $request->user()->id)
            ->where('type', SlotAssignment::TYPE_REQUEST)
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            return response()->json([
                'message' => 'You already requested this slot.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot->assignments()->create([
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $request->user()->id,
            'type' => SlotAssignment::TYPE_REQUEST,
            'status' => SlotAssignment::STATUS_PENDING,
            'message' => $validated['message'] ?? null,
        ]);

        return response()->json([
            'message' => 'Request submitted to set owner.',
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ], 201);
    }

    public function proposeSlot(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        if (! $set->signups_open) {
            return response()->json([
                'message' => 'Sign ups are closed for this set.',
            ], 422);
        }

        if (! $slot->isOpen() && ! (bool) $slot->is_claimable_manual) {
            return response()->json([
                'message' => 'Recommendations are only available for open or claimable slots.',
            ], 422);
        }

        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('hide_from_slot_proposals', false)
                    ->where('is_deleted_account', false)),
            ],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $targetUserId = (int) $validated['target_user_id'];
        if ($targetUserId === (int) $request->user()->id) {
            return response()->json([
                'message' => 'Choose someone else for a recommendation.',
            ], 422);
        }

        $slot->assignments()->create([
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $targetUserId,
            'type' => SlotAssignment::TYPE_PROPOSAL,
            'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
            'message' => $validated['message'] ?? null,
        ]);

        return response()->json([
            'message' => 'Recommendation sent.',
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ], 201);
    }

    public function releaseSlot(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        $isSetManager = $this->isPlannedSetManager($request->user(), $set);
        $isAssignee = (int) $slot->user_id === (int) $request->user()->id;

        if (! $isSetManager && ! $isAssignee) {
            abort(403);
        }

        if ($slot->isOpen()) {
            return response()->json([
                'message' => 'This slot is already open.',
                'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
            ]);
        }

        $slot->update([
            'user_id' => null,
            'manual_performer_name' => null,
            'is_claimable_manual' => false,
        ]);

        return response()->json([
            'message' => 'Slot released.',
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    public function updateSlot(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('managePlanned', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'in:'.implode(',', Slot::keys())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))],
            'manual_performer_name' => ['nullable', 'string', 'max:255'],
            'replace_conflicting_assignment' => ['nullable', 'boolean'],
        ]);

        $conflictingSlot = null;
        if (! empty($validated['user_id'])) {
            $conflictingSlot = SlotCompatibility::conflictingSlotForSlot((int) $validated['user_id'], $slot, $validated['name']);

            if ($conflictingSlot && ! ($validated['replace_conflicting_assignment'] ?? false)) {
                $slotOptions = Slot::options();
                $conflictingLabel = $slotOptions[$conflictingSlot->name] ?? $conflictingSlot->name;
                $targetLabel = $slotOptions[$validated['name']] ?? $validated['name'];
                $playerName = User::query()->find($validated['user_id'])?->name ?? 'This player';
                $message = "$playerName is already assigned to $conflictingLabel on this song. Moving them to $targetLabel will clear that assignment.";

                return response()->json([
                    'message' => $message,
                    'conflict' => [
                        'slot_id' => $conflictingSlot->id,
                        'slot_label' => $conflictingLabel,
                    ],
                ], 409);
            }
        }

        $manualPerformerName = trim((string) ($validated['manual_performer_name'] ?? ''));
        if (! empty($validated['user_id'])) {
            $manualPerformerName = '';
        }

        $previousUserId = $slot->user_id;
        $incomingUserId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;
        $resetClaimableOnAssignment = $incomingUserId !== null && (int) $previousUserId !== $incomingUserId;

        DB::transaction(function () use ($slot, $validated, $manualPerformerName, $conflictingSlot, $resetClaimableOnAssignment): void {
            if ($conflictingSlot) {
                $conflictingSlot->update([
                    'user_id' => null,
                    'manual_performer_name' => null,
                ]);
            }

            $slotAttributes = [
                'name' => $validated['name'],
                'notes' => $validated['notes'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'manual_performer_name' => $manualPerformerName !== '' ? $manualPerformerName : null,
            ];

            if ($resetClaimableOnAssignment) {
                $slotAttributes['is_claimable_manual'] = false;
            }

            $slot->update($slotAttributes);

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

        return response()->json([
            'message' => 'Slot updated.',
            'slot' => $this->toSlotPayload($slot->fresh('user', 'assignments'), null, $request->user()),
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    public function updateSlotClaimable(Request $request, Set $set, Slot $slot): JsonResponse
    {
        $this->authorize('view', $set);

        if (! $set->isDraft()) {
            return response()->json([
                'message' => 'Only draft sets can be edited here.',
            ], 422);
        }

        $slot->loadMissing('song.set');
        if ((int) $slot->song->set_id !== (int) $set->id) {
            abort(404);
        }

        $isSetManager = $this->isPlannedSetManager($request->user(), $set);
        $isAssignee = (int) $slot->user_id === (int) $request->user()->id;

        if (! $isSetManager && ! $isAssignee) {
            abort(403);
        }

        if ($slot->user_id === null) {
            return response()->json([
                'message' => 'Only assigned slots can be marked claimable.',
            ], 422);
        }

        $validated = $request->validate([
            'is_claimable_manual' => ['required', 'boolean'],
        ]);

        $newValue = (bool) $validated['is_claimable_manual'];

        $slot->update([
            'is_claimable_manual' => $newValue,
        ]);

        return response()->json([
            'message' => $newValue ? 'Slot marked claimable.' : 'Slot claimable status removed.',
            'song' => $this->toSongPayload($slot->song->fresh('slots.user', 'slots.assignments'), $request->user()),
        ]);
    }

    /**
     * @param  array<int, string>  $collaboratorNames
     * @param  Collection<int, JamSession>  $attendanceSessions
     * @param  array<int, array<int, string>>  $attendanceMap
     * @return array<string, mixed>
     */
    private function toSetPayload(Set $set, User $viewer, array $collaboratorNames, $attendanceSessions, array $attendanceMap): array
    {
        $slotOptions = Slot::options();
        $collaboratorIds = $set->collaboratorUserIds();
        $participantIds = $this->participantUserIdsForSet($set);
        $slotParticipantIds = $set->songs
            ->flatMap(fn (Song $song) => $song->slots->pluck('user_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $manualPerformerNames = $set->songs
            ->flatMap(fn (Song $song) => $song->slots->pluck('manual_performer_name'))
            ->filter(fn (?string $name): bool => filled($name))
            ->map(fn (string $name): string => trim($name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        $canVote = in_array($viewer->id, $participantIds, true);

        $attendanceBySession = $attendanceSessions
            ->map(function (JamSession $session) use ($attendanceMap, $participantIds, $viewer, $set, $collaboratorIds, $collaboratorNames, $slotParticipantIds, $manualPerformerNames): array {
                $sessionStatuses = $attendanceMap[$session->id] ?? [];
                $counts = [
                    JamSessionAttendance::STATUS_GOING => 0,
                    JamSessionAttendance::STATUS_NOT_GOING => 0,
                    JamSessionAttendance::STATUS_MAYBE => 0,
                ];

                foreach ($participantIds as $participantId) {
                    $status = $sessionStatuses[$participantId] ?? JamSessionAttendance::STATUS_MAYBE;
                    $counts[$status]++;
                }

                $ownerStatus = $sessionStatuses[(int) $set->owner_id] ?? JamSessionAttendance::STATUS_MAYBE;
                $collaboratorGoingCount = collect($collaboratorIds)
                    ->filter(fn (int $collaboratorId): bool => ($sessionStatuses[$collaboratorId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_GOING)
                    ->count();
                $collaboratorNotGoingCount = collect($collaboratorIds)
                    ->filter(fn (int $collaboratorId): bool => ($sessionStatuses[$collaboratorId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_NOT_GOING)
                    ->count();
                $allCollaboratorsUnavailable = $collaboratorIds !== []
                    && $collaboratorNotGoingCount === count($collaboratorIds);

                $goingNames = collect($participantIds)
                    ->filter(fn (int $participantId): bool => ($sessionStatuses[$participantId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_GOING)
                    ->map(fn (int $participantId): string => $collaboratorNames[$participantId] ?? 'Unknown user')
                    ->values();

                $notGoingNames = collect($participantIds)
                    ->filter(fn (int $participantId): bool => ($sessionStatuses[$participantId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_NOT_GOING)
                    ->map(fn (int $participantId): string => $collaboratorNames[$participantId] ?? 'Unknown user')
                    ->values();

                $notSpecifiedSlotNames = collect($slotParticipantIds)
                    ->filter(fn (int $participantId): bool => ($sessionStatuses[$participantId] ?? JamSessionAttendance::STATUS_MAYBE) === JamSessionAttendance::STATUS_MAYBE)
                    ->map(fn (int $participantId): string => $collaboratorNames[$participantId] ?? 'Unknown user')
                    ->values();

                $goingNames = $goingNames
                    ->merge($manualPerformerNames)
                    ->map(fn (string $name): string => trim($name))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->unique()
                    ->values();

                $notGoingNames = $notGoingNames
                    ->map(fn (string $name): string => trim($name))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->unique()
                    ->values();

                $notSpecifiedSlotNames = $notSpecifiedSlotNames
                    ->map(fn (string $name): string => trim($name))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->unique()
                    ->values();

                return [
                    'jam_session_id' => $session->id,
                    'jam_session_name' => $session->name,
                    'jam_session_date_label' => $session->date->format('D, M j, Y'),
                    'is_closed' => (bool) $session->is_closed,
                    'my_status' => $sessionStatuses[$viewer->id] ?? JamSessionAttendance::STATUS_MAYBE,
                    'owner_status' => $ownerStatus,
                    'owner_unavailable' => $ownerStatus === JamSessionAttendance::STATUS_NOT_GOING,
                    'collaborator_going_count' => $collaboratorGoingCount,
                    'collaborator_not_going_count' => $collaboratorNotGoingCount,
                    'collaborator_total_count' => count($collaboratorIds),
                    'all_collaborators_unavailable' => $allCollaboratorsUnavailable,
                    'going_names' => $goingNames->all(),
                    'not_going_names' => $notGoingNames->all(),
                    'not_specified_slot_names' => $notSpecifiedSlotNames->all(),
                    'display_counts' => [
                        'going' => $goingNames->count(),
                        'not_going' => $notGoingNames->count(),
                        'not_specified' => $notSpecifiedSlotNames->count(),
                    ],
                    'counts' => [
                        'going' => $counts[JamSessionAttendance::STATUS_GOING],
                        'not_going' => $counts[JamSessionAttendance::STATUS_NOT_GOING],
                        'maybe' => $counts[JamSessionAttendance::STATUS_MAYBE],
                        'total' => count($participantIds),
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $set->id,
            'name' => $set->name,
            'description' => $set->description,
            'performed' => (bool) $set->performed,
            'is_hidden' => (bool) $set->is_hidden,
            'free_for_all' => (bool) $set->free_for_all,
            'song_requests' => (bool) $set->song_requests,
            'signups_open' => (bool) $set->signups_open,
            'has_attachments' => ((int) ($set->attachments_count ?? 0)) > 0,
            'owner' => [
                'id' => $set->owner?->id,
                'name' => $set->owner?->name,
            ],
            'collaborator_ids' => $set->collaboratorUserIds(),
            'collaborators' => collect($set->collaboratorUserIds())
                ->map(fn (int $id): array => [
                    'id' => $id,
                    'name' => $collaboratorNames[$id] ?? 'Unknown user',
                ])
                ->values()
                ->all(),
            'candidate_session_ids' => collect($set->candidate_session_ids ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all(),
            'participant_ids' => $participantIds,
            'attendance_sessions' => $attendanceBySession,
            'songs' => $set->songs
                ->map(fn (Song $song): array => $this->toSongPayload($song, $viewer))
                ->values()
                ->all(),
            'pending_song_requests' => $set->songRequests
                ->where('status', SongRequest::STATUS_PENDING)
                ->map(fn (SongRequest $songRequest): array => [
                    'id' => $songRequest->id,
                    'artist' => $songRequest->artist,
                    'title' => $songRequest->title,
                    'notes' => $songRequest->notes,
                    'requester_user_id' => (int) $songRequest->requester_user_id,
                    'requester_name' => $songRequest->requester?->name ?? 'Unknown user',
                    'jam_standard_song_id' => $songRequest->jam_standard_song_id,
                    'band_template_id' => $songRequest->band_template_id,
                    'band_template_name' => $songRequest->bandTemplate?->name,
                    'requested_slot_names' => collect($songRequest->requested_slot_names ?? [])->values()->all(),
                    'requested_slot_labels' => collect($songRequest->requested_slot_names ?? [])
                        ->map(fn (string $slotName): string => $slotOptions[$slotName] ?? str($slotName)->replace('_', ' ')->title())
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'pending_slot_requests' => $set->songs
                ->flatMap(fn (Song $song) => $song->slots->flatMap(fn (Slot $slot) => $slot->assignments
                    ->where('status', SlotAssignment::STATUS_PENDING)
                    ->map(fn (SlotAssignment $assignment): array => [
                        'id' => $assignment->id,
                        'type' => $assignment->type,
                        'status' => $assignment->status,
                        'message' => $assignment->message,
                        'actor_user_id' => (int) $assignment->actor_user_id,
                        'actor_name' => $assignment->actor?->name ?? 'Unknown user',
                        'target_user_id' => (int) $assignment->target_user_id,
                        'target_name' => $assignment->target?->name ?? 'Unknown user',
                        'slot_id' => $slot->id,
                        'slot_name' => $slot->name,
                        'slot_label' => $slotOptions[$slot->name] ?? $slot->name,
                        'song_id' => $song->id,
                        'song_artist' => $song->artist,
                        'song_title' => $song->title,
                    ])))
                ->sortByDesc('id')
                ->values()
                ->all(),
            'can_manage' => $viewer->is_admin || $set->owner_id === $viewer->id || $set->isCollaborator($viewer),
            'can_edit' => $viewer->is_admin || $set->owner_id === $viewer->id,
            'can_manage_collaborators' => $viewer->is_admin || $set->owner_id === $viewer->id,
            'can_vote' => $canVote,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toSongPayload(Song $song, ?User $viewer = null): array
    {
        $slotOptions = Slot::options();

        return [
            'id' => $song->id,
            'artist' => $song->artist,
            'title' => $song->title,
            'notes' => $song->notes,
            'duration' => $song->duration,
            'source' => $song->source,
            'slots' => $song->slots
                ->map(fn (Slot $slot): array => $this->toSlotPayload($slot, $slotOptions, $viewer))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, string>|null  $slotOptions
     * @return array<string, mixed>
     */
    private function toSlotPayload(Slot $slot, ?array $slotOptions = null, ?User $viewer = null): array
    {
        $options = $slotOptions ?? Slot::options();
        $slot->loadMissing('assignments', 'user');

        $hasPendingOwnRequest = $viewer
            ? $slot->assignments->contains(fn (SlotAssignment $assignment): bool => $assignment->status === SlotAssignment::STATUS_PENDING
                && $assignment->type === SlotAssignment::TYPE_REQUEST
                && (int) $assignment->actor_user_id === (int) $viewer->id
                && (int) $assignment->target_user_id === (int) $viewer->id)
            : false;

        return [
            'id' => $slot->id,
            'name' => $slot->name,
            'label' => $options[$slot->name] ?? $slot->name,
            'notes' => $slot->notes,
            'user_id' => $slot->user_id,
            'user_name' => $slot->assignedPerformerName(),
            'manual_performer_name' => $slot->manual_performer_name,
            'is_open' => $slot->isOpen(),
            'is_claimable_manual' => (bool) $slot->is_claimable_manual,
            'has_pending_own_request' => $hasPendingOwnRequest,
        ];
    }

    /**
     * @param  array<int, int|string>  $collaboratorIds
     * @return array<int>
     */
    private function normalizedCollaboratorIds(array $collaboratorIds, int $ownerId): array
    {
        return collect($collaboratorIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $ownerId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $candidateSessionIds
     * @return array<int>
     */
    private function normalizedCandidateSessionIds(array $candidateSessionIds, User $user): array
    {
        $normalizedIds = collect($candidateSessionIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return [];
        }

        $validIds = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->when(! $user->is_admin, fn ($query) => $query->whereDate('date', '>=', today()))
            ->whereIn('id', $normalizedIds->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $invalidIds = $normalizedIds
            ->reject(fn (int $id): bool => in_array($id, $validIds, true))
            ->values();

        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'candidate_session_ids' => 'Choose only visible upcoming jam sessions as candidate options.',
            ]);
        }

        return $normalizedIds->all();
    }

    private function isPlannedSetManager(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    /**
     * @return array<int>
     */
    private function participantUserIdsForSet(Set $set): array
    {
        $set->loadMissing('songs.slots');

        $slotAssigneeIds = $set->songs
            ->flatMap(fn (Song $song) => $song->slots->pluck('user_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return collect([$set->owner_id, ...$set->collaboratorUserIds(), ...$slotAssigneeIds])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    private function assignSlotAndReleaseConflictsForPlanned(SlotAssignment $slotAssignment): array
    {
        $slotAssignment->loadMissing('slot.song');

        $conflictingSlot = SlotCompatibility::conflictingSlotForSlot($slotAssignment->target_user_id, $slotAssignment->slot);

        if ($conflictingSlot) {
            $conflictingSlot->update([
                'user_id' => null,
                'manual_performer_name' => null,
            ]);
        }

        $slotAssignment->slot->update([
            'user_id' => $slotAssignment->target_user_id,
            'manual_performer_name' => null,
            'is_claimable_manual' => false,
        ]);

        $supersededIds = SlotAssignment::query()
            ->whereKeyNot($slotAssignment->id)
            ->where('actor_user_id', $slotAssignment->target_user_id)
            ->where('target_user_id', $slotAssignment->target_user_id)
            ->where('type', SlotAssignment::TYPE_REQUEST)
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot', fn ($query) => $query->where('song_id', $slotAssignment->slot->song_id))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($supersededIds !== []) {
            SlotAssignment::query()
                ->whereIn('id', $supersededIds)
                ->update([
                    'status' => SlotAssignment::STATUS_REJECTED,
                    'responded_at' => now(),
                ]);
        }

        return $supersededIds;
    }

    /**
     * @return array<string, list<string>>
     */
    private function slotConflicts(): array
    {
        return collect(SlotType::query()
            ->with('conflicts:key')
            ->where('active', true)
            ->get(['id', 'key'])
            ->reduce(function (array $conflicts, SlotType $slotType): array {
                $conflicts[$slotType->key] ??= [];

                foreach ($slotType->conflicts->pluck('key') as $conflictingKey) {
                    $conflicts[$slotType->key][] = $conflictingKey;
                    $conflicts[$conflictingKey][] = $slotType->key;
                }

                return $conflicts;
            }, []))
            ->map(fn (array $conflictingKeys) => collect($conflictingKeys)->unique()->values()->all())
            ->all();
    }
}
