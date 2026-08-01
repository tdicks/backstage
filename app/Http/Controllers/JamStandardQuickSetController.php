<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\JamStandardSong;
use App\Models\JamStandardUserSlot;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\Song;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SlotCompatibility;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JamStandardQuickSetController extends Controller
{
    public function storeUser(Request $request): RedirectResponse
    {
        $this->authorize('create', Set::class);

        $validated = $request->validate([
            'jam_session_id' => ['required', 'integer', 'exists:jam_sessions,id'],
            'set_name' => ['required', 'string', 'max:255'],
            'is_hidden' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
            'catalog_song_ids' => ['required', 'array', 'min:1'],
            'catalog_song_ids.*' => ['integer', 'exists:jam_standard_songs,id'],
            'song_slots' => ['nullable', 'array'],
            'song_slots.*' => ['nullable', 'array'],
            'song_slots.*.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $jamSession = JamSession::query()
            ->visibleTo($request->user())
            ->findOrFail((int) $validated['jam_session_id']);

        if ($jamSession->is_closed && ! $request->user()->is_admin) {
            return back()->with('status', 'This jam session is closed. No new sets can be created.');
        }

        $songSelections = $this->selectedCatalogSongs($validated['catalog_song_ids']);

        if ($songSelections->isEmpty()) {
            return back()->with('status', 'Choose at least one active catalog song.');
        }

        $this->ensureSelectedSlotsBelongToCatalogSongs($songSelections, $validated['song_slots'] ?? [], 'song_slots');

        $set = DB::transaction(function () use ($validated, $request, $jamSession, $songSelections) {
            $set = $jamSession->sets()->create([
                'name' => $validated['set_name'],
                'owner_id' => $request->user()->id,
                'position' => ((int) $jamSession->sets()->max('position')) + 1,
                'performed' => false,
                'song_requests' => true,
                'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
                'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            ]);

            $nextSongPosition = 1;
            $coveredSlots = [];

            foreach ($songSelections as $catalogSong) {
                $song = Song::query()->create([
                    'set_id' => $set->id,
                    'jam_standard_song_id' => $catalogSong->id,
                    'artist' => $catalogSong->artist,
                    'title' => $catalogSong->title,
                    'notes' => $catalogSong->notes,
                    'duration' => $catalogSong->duration,
                    'source' => $catalogSong->source,
                    'position' => $nextSongPosition++,
                ]);

                $selectedSlots = collect($validated['song_slots'][$catalogSong->id] ?? [])
                    ->map(fn ($slotName) => (string) $slotName)
                    ->intersect($catalogSong->slots->pluck('name'))
                    ->unique()
                    ->values();

                $nextSlotPosition = 1;

                foreach ($catalogSong->slots as $catalogSlot) {
                    $slotName = $catalogSlot->name;
                    $userId = null;

                    if ($selectedSlots->contains($slotName)) {
                        SlotCompatibility::ensureUserCanPerformSlotInSong($request->user()->id, $song, $slotName, field: 'song_slots.'.$catalogSong->id);
                        $userId = $request->user()->id;
                        $coveredSlots[] = $slotName;
                    }

                    $song->slots()->create([
                        'name' => $slotName,
                        'user_id' => $userId,
                        'position' => $nextSlotPosition++,
                    ]);
                }
            }

            if ($coveredSlots !== []) {
                $this->mergeCoverage($request->user(), $coveredSlots);
            }

            return $set;
        });

        $recipientIds = JamStandardUserSlot::query()
            ->whereIn('jam_standard_song_id', $songSelections->pluck('id'))
            ->whereIn('slot_name', collect($validated['song_slots'] ?? [])->flatten())
            ->pluck('user_id')
            ->unique();

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SET_CREATED,
            User::query()->whereIn('id', $recipientIds)->get(),
            $request->user(),
            [
                'title' => 'New quick set created',
                'body' => $request->user()->name.' created '.$set->name.' with songs you can perform.',
                'action_url' => route('sessions.show', $jamSession).'#set-'.$set->id,
                'action_label' => 'Open set',
            ]
        );

        return to_route('sessions.show', $jamSession)
            ->with('status', 'Quick set created from Jam Standards.')
            ->withFragment('set-'.$set->id);
    }

    public function storeLive(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'jam_session_id' => ['required', 'integer', 'exists:jam_sessions,id'],
            'is_hidden' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
            'catalog_song_ids' => ['required', 'array', 'min:1'],
            'catalog_song_ids.*' => ['integer', 'exists:jam_standard_songs,id'],
            'live_song_assignments' => ['nullable', 'array'],
            'live_song_assignments.*' => ['nullable', 'array'],
            'live_song_assignments.*.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $jamSession = JamSession::query()->findOrFail((int) $validated['jam_session_id']);
        abort_unless((int) $jamSession->jam_manager_id === (int) $request->user()->id, 403);
        $songSelections = $this->selectedCatalogSongs($validated['catalog_song_ids']);

        if ($songSelections->isEmpty()) {
            return back()->with('status', 'Choose at least one active catalog song.');
        }

        $this->ensureLiveAssignmentsBelongToCatalogSongs($songSelections, $validated['live_song_assignments'] ?? []);
        $this->ensureLiveAssignmentsUseAvailableAttendees(
            $jamSession,
            $validated['live_song_assignments'] ?? [],
        );
        $this->ensureLiveAssignmentsDoNotConflict($validated['live_song_assignments'] ?? []);

        $set = DB::transaction(function () use ($validated, $request, $jamSession, $songSelections) {
            $set = $jamSession->sets()->create([
                'name' => $this->liveQuickSetName($songSelections),
                'owner_id' => $request->user()->id,
                'position' => ((int) $jamSession->sets()->max('position')) + 1,
                'performed' => false,
                'song_requests' => true,
                'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
                'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            ]);

            $nextSongPosition = 1;

            foreach ($songSelections as $catalogSong) {
                $song = Song::query()->create([
                    'set_id' => $set->id,
                    'jam_standard_song_id' => $catalogSong->id,
                    'artist' => $catalogSong->artist,
                    'title' => $catalogSong->title,
                    'notes' => $catalogSong->notes,
                    'duration' => $catalogSong->duration,
                    'source' => $catalogSong->source,
                    'position' => $nextSongPosition++,
                ]);

                $slotAssignments = collect($validated['live_song_assignments'][$catalogSong->id] ?? []);
                $nextSlotPosition = 1;

                foreach ($catalogSong->slots as $catalogSlot) {
                    $slotName = $catalogSlot->name;
                    $assignedUserId = $slotAssignments->get($slotName);
                    $userId = null;

                    if ($assignedUserId !== null && $assignedUserId !== '') {
                        $userId = User::query()->whereKey((int) $assignedUserId)->exists()
                            ? (int) $assignedUserId
                            : null;
                    }

                    $song->slots()->create([
                        'name' => $slotName,
                        'user_id' => $userId,
                        'position' => $nextSlotPosition++,
                    ]);
                }
            }

            return $set;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Live quick set created from Jam Standards.',
                'set' => $set->only(['id', 'name', 'position']),
            ], 201);
        }

        return to_route('sessions.show', $jamSession)
            ->with('status', 'Live quick set created from Jam Standards.')
            ->withFragment('set-'.$set->id);
    }

    private function selectedCatalogSongs(array $catalogSongIds)
    {
        $orderedIds = collect($catalogSongIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $songs = JamStandardSong::query()
            ->active()
            ->whereIn('id', $orderedIds)
            ->with('slots')
            ->get()
            ->keyBy('id');

        return $orderedIds
            ->map(fn (int $songId) => $songs->get($songId))
            ->filter();
    }

    private function liveQuickSetName($catalogSongs): string
    {
        return $catalogSongs
            ->countBy(fn (JamStandardSong $song): string => $song->artist)
            ->sortDesc()
            ->keys()
            ->implode('/');
    }

    /** @param array<int|string, array<int, string>> $selectedSlots */
    private function ensureSelectedSlotsBelongToCatalogSongs($catalogSongs, array $selectedSlots, string $inputName): void
    {
        foreach ($catalogSongs as $catalogSong) {
            $catalogSlotNames = $catalogSong->slots->pluck('name');
            $invalidSlotNames = collect($selectedSlots[$catalogSong->id] ?? [])
                ->diff($catalogSlotNames)
                ->values();

            if ($invalidSlotNames->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $inputName.'.'.$catalogSong->id => 'Choose only slots defined for '.$catalogSong->artist.' - '.$catalogSong->title.'.',
                ]);
            }
        }
    }

    /** @param array<int|string, array<string, int|string>> $assignments */
    private function ensureLiveAssignmentsBelongToCatalogSongs($catalogSongs, array $assignments): void
    {
        foreach ($catalogSongs as $catalogSong) {
            $invalidSlotNames = collect($assignments[$catalogSong->id] ?? [])
                ->keys()
                ->diff($catalogSong->slots->pluck('name'))
                ->values();

            if ($invalidSlotNames->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'live_song_assignments.'.$catalogSong->id => 'Assign only slots defined for '.$catalogSong->artist.' - '.$catalogSong->title.'.',
                ]);
            }
        }
    }

    /** @param array<int|string, array<string, int|string>> $assignments */
    private function ensureLiveAssignmentsUseAvailableAttendees(JamSession $jamSession, array $assignments): void
    {
        $checkedInUsers = $jamSession->signIns()
            ->whereNotNull('signed_in_at')
            ->with('user')
            ->get()
            ->mapWithKeys(fn ($signIn) => [$signIn->user_id => $signIn->user]);

        foreach ($assignments as $songId => $songAssignments) {
            foreach ($songAssignments as $slotName => $assignedUserId) {

                if ($assignedUserId === null || $assignedUserId === '') {
                    continue;
                }

                $assignedUser = $checkedInUsers->get((int) $assignedUserId);

                if ($assignedUser === null || $assignedUser->willNotCoverSlot($slotName)) {
                    throw ValidationException::withMessages([
                        'live_song_assignments.'.$songId.'.'.$slotName => 'Choose a checked-in user who is available for this slot.',
                    ]);
                }
            }
        }
    }

    /** @param array<int|string, array<string, int|string>> $assignments */
    private function ensureLiveAssignmentsDoNotConflict(array $assignments): void
    {
        $conflicts = SlotType::query()
            ->with('conflicts:key')
            ->where('active', true)
            ->get(['id', 'key'])
            ->reduce(function (array $conflicts, SlotType $slotType): array {
                foreach ($slotType->conflicts->pluck('key') as $conflictingKey) {
                    $conflicts[$slotType->key][] = $conflictingKey;
                    $conflicts[$conflictingKey][] = $slotType->key;
                }

                return $conflicts;
            }, []);

        foreach ($assignments as $songId => $songAssignments) {
            foreach ($songAssignments as $slotName => $assignedUserId) {
                if ($assignedUserId === null || $assignedUserId === '') {
                    continue;
                }

                $conflictingAssignments = collect($songAssignments)
                    ->except($slotName)
                    ->filter(fn ($otherUserId, $otherSlotName) => (int) $otherUserId === (int) $assignedUserId
                        && in_array($otherSlotName, $conflicts[$slotName] ?? [], true));

                if ($conflictingAssignments->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'live_song_assignments.'.$songId.'.'.$slotName => 'This performer is already assigned to an incompatible slot on this song.',
                    ]);
                }
            }
        }
    }

    private function mergeCoverage(User $user, array $slotNames): void
    {
        $coverageMap = $user->slotCoverageMap();

        foreach ($slotNames as $slotName) {
            $coverageMap[$slotName] = User::SLOT_COVERAGE_CAN;
        }

        $user->forceFill([
            'slot_coverage' => $coverageMap,
        ])->save();
    }
}
