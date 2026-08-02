<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSessionAttendance;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use App\Services\JamSessionAttendanceService;
use App\Services\NotificationService;
use App\Services\SlotCompatibility;
use App\SessionCardFragment;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    public function store(Request $request, Song $song): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $song);

        $attendanceService = app(JamSessionAttendanceService::class);

        $validated = $request->validate([
            'addition_mode' => ['nullable', 'string', 'in:individual,template'],
            'name' => ['nullable', 'string', 'in:'.implode(',', Slot::keys()), 'required_unless:addition_mode,template', 'prohibited_if:addition_mode,template'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id', 'required_if:addition_mode,template', 'prohibited_unless:addition_mode,template'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))],
        ]);

        if (($validated['addition_mode'] ?? 'individual') === 'template') {
            $template = BandTemplate::query()
                ->with('slots')
                ->findOrFail($validated['band_template_id']);
            $existingSlotNames = $song->slots()->pluck('name')->all();
            $slotNames = $template->slots
                ->pluck('name')
                ->unique()
                ->reject(fn (string $slotName) => in_array($slotName, $existingSlotNames, true));
            $nextPosition = ((int) $song->slots()->max('position')) + 1;

            $createdSlots = $slotNames->map(function (string $slotName) use ($song, &$nextPosition) {
                return $song->slots()->create([
                    'name' => $slotName,
                    'position' => $nextPosition++,
                ]);
            });

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Band template applied.',
                    'html' => $createdSlots
                        ->map(fn (Slot $slot) => app(SessionCardFragment::class)->slot($slot, $request->user()))
                        ->values(),
                ], 201);
            }

            return back()->with('status', 'Band template applied.');
        }

        if (! empty($validated['user_id'])) {
            $assignedUser = User::query()->find((int) $validated['user_id']);

            if ($assignedUser && $attendanceService->isNotGoing($song->set->session, $assignedUser)) {
                if ($request->user()->is_admin) {
                    $attendanceService->resetToMaybeForAdminAssignment($song->set->session, $assignedUser);
                } else {
                    return back()->withErrors([
                        'user_id' => 'This user marked themselves as not attending this session and cannot be assigned.',
                    ]);
                }
            }

            SlotCompatibility::ensureUserCanPerformSlotInSong((int) $validated['user_id'], $song, $validated['name']);
        }

        $nextPosition = ((int) $song->slots()->max('position')) + 1;

        $slot = $song->slots()->create([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'position' => $nextPosition,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot added.',
                'html' => [app(SessionCardFragment::class)->slot($slot, $request->user())],
            ], 201);
        }

        return back()->with('status', 'Slot added.');
    }

    public function update(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $slot);

        $attendanceService = app(JamSessionAttendanceService::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'in:'.implode(',', Slot::keys())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))],
            'manual_performer_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'replace_conflicting_assignment' => ['nullable', 'boolean'],
        ]);

        $conflictingSlot = null;
        if (! empty($validated['user_id'])) {
            $assignedUser = User::query()->find((int) $validated['user_id']);

            if ($assignedUser && $attendanceService->isNotGoing($slot->song->set->session, $assignedUser)) {
                if ($request->user()->is_admin) {
                    $attendanceService->resetToMaybeForAdminAssignment($slot->song->set->session, $assignedUser);
                } else {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'This user marked themselves as not attending this session and cannot be assigned.',
                            'errors' => [
                                'user_id' => ['This user marked themselves as not attending this session and cannot be assigned.'],
                            ],
                        ], 422);
                    }

                    return back()->withErrors([
                        'user_id' => 'This user marked themselves as not attending this session and cannot be assigned.',
                    ]);
                }
            }

            $conflictingSlot = SlotCompatibility::conflictingSlotForSlot((int) $validated['user_id'], $slot, $validated['name']);

            if ($conflictingSlot && ! ($validated['replace_conflicting_assignment'] ?? false)) {
                $slotOptions = Slot::options();
                $conflictingLabel = $slotOptions[$conflictingSlot->name] ?? $conflictingSlot->name;
                $targetLabel = $slotOptions[$validated['name']] ?? $validated['name'];
                $playerName = User::query()->find($validated['user_id'])?->name ?? 'This player';
                $message = "$playerName is already assigned to $conflictingLabel on this song. Moving them to $targetLabel will clear that assignment.";

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'conflict' => [
                            'slot_id' => $conflictingSlot->id,
                            'slot_label' => $conflictingLabel,
                        ],
                    ], 409);
                }

                return back()->withErrors(['user_id' => $message]);
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
                'position' => $validated['position'] ?? $slot->position,
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

        // Notify user if they were newly assigned to the slot
        if (! empty($validated['user_id']) && $validated['user_id'] !== $previousUserId) {
            $slot->load('user', 'song');
            app(NotificationService::class)->notifyUsers(
                NotificationTypeCatalog::SLOT_MANUALLY_ASSIGNED,
                [$slot->user],
                $request->user(),
                [
                    'title' => 'You\'ve been assigned to a slot',
                    'body' => 'You\'ve been assigned the '.(Slot::options()[$slot->name] ?? $slot->name).' slot on '.$slot->song->artist.' - '.$slot->song->title.'.',
                    'action_url' => route('sessions.show', $slot->song->set->session).'#slot-'.$slot->id,
                    'action_label' => 'View slot',
                ]
            );
        }

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

        $set = $slot->song->set;
        $user = $request->user();
        $attendanceService = app(JamSessionAttendanceService::class);
        $isSetManager = $set->owner_id === $user->id || $set->isCollaborator($user) || $user->is_admin;
        $isCollaborator = $set->isCollaborator($user);
        $slotIsClaimable = $attendanceService->slotIsClaimable($slot);
        $canFreeTake = $set->free_for_all && ($slot->isOpen() || $slotIsClaimable);

        if (! $user->is_admin && $attendanceService->isNotGoing($set->session, $user)) {
            $message = 'You marked yourself as not attending this session. Set your attendance to Maybe or Going before claiming slots.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('status', $message);
        }

        abort_if($set->performed, 403);

        if (! $set->signups_open && ! $isSetManager) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        if (! $isSetManager && ! $canFreeTake) {
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

        DB::transaction(function () use ($slot, $request): void {
            $slot->update([
                'user_id' => $request->user()->id,
                'manual_performer_name' => null,
                'is_claimable_manual' => false,
            ]);

            // Clear only the claiming user's pending self-request for this slot.
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

        $attendanceService->markGoingIfAllowed($set->session, $user, JamSessionAttendance::SOURCE_AUTO_SLOT);

        // Notify set owner/collaborators if slot was taken without approval (collaborator or free for all mode)
        if ($isCollaborator || $canFreeTake) {
            $slot->load('user', 'song');
            $slotLabel = Slot::options()[$slot->name] ?? $slot->name;
            $songTitle = $slot->song->artist.' - '.$slot->song->title;

            if ($isCollaborator) {
                $body = $request->user()->name.' took the '.$slotLabel.' slot on '.$songTitle.' as a collaborator.';
            } else {
                $body = $request->user()->name.' claimed the '.$slotLabel.' slot on '.$songTitle.' (free for all mode).';
            }

            app(NotificationService::class)->notifyUsers(
                NotificationTypeCatalog::SLOT_TAKEN_WITHOUT_APPROVAL,
                app(NotificationService::class)->managersForSet($set),
                $request->user(),
                [
                    'title' => 'Slot taken on your set',
                    'body' => $body,
                    'action_url' => route('sessions.show', $set->session).'#slot-'.$slot->id,
                    'action_label' => 'Open slot',
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Slot assigned to you.',
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', 'Slot assigned to you.');
    }

    public function updateClaimable(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        $slot->loadMissing('song.set.session');

        $user = $request->user();
        $set = $slot->song->set;
        $isSetManager = $user->is_admin
            || $set->owner_id === $user->id
            || $set->isCollaborator($user)
            || $set->session->jam_manager_id === $user->id;
        $isAssignee = (int) $slot->user_id === (int) $user->id;

        if (! $isSetManager && ! $isAssignee) {
            abort(403);
        }

        if ($slot->song->set->session->is_closed && ! $user->is_admin) {
            $message = 'This session is closed. Slot claimability can only be changed by an admin.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('status', $message);
        }

        if ($slot->user_id === null) {
            $message = 'Only assigned slots can be marked claimable.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('status', $message);
        }

        $validated = $request->validate([
            'is_claimable_manual' => ['required', 'boolean'],
        ]);

        $newValue = (bool) $validated['is_claimable_manual'];
        $wasClaimable = (bool) $slot->is_claimable_manual;

        $slot->update([
            'is_claimable_manual' => $newValue,
        ]);

        if ($newValue && ! $wasClaimable) {
            $pendingRequesterIds = SlotAssignment::query()
                ->where('slot_id', $slot->id)
                ->where('type', SlotAssignment::TYPE_REQUEST)
                ->where('status', SlotAssignment::STATUS_PENDING)
                ->pluck('actor_user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($pendingRequesterIds->isNotEmpty()) {
                $requesters = User::query()
                    ->whereIn('id', $pendingRequesterIds->all())
                    ->orderBy('name')
                    ->get();

                app(NotificationService::class)->notifyUsers(
                    NotificationTypeCatalog::SLOT_REQUEST_CLAIMABLE,
                    $requesters,
                    $user,
                    [
                        'title' => 'Requested slot is now claimable',
                        'body' => $slot->assignedPerformerName().' marked the '.(Slot::options()[$slot->name] ?? $slot->name).' slot on '.$slot->song->artist.' - '.$slot->song->title.' as claimable.',
                        'action_url' => route('sessions.show', $slot->song->set->session).'#slot-'.$slot->id,
                        'action_label' => 'Open slot',
                    ]
                );
            }
        }

        $message = $newValue ? 'Slot marked claimable.' : 'Slot claimable status removed.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'slot' => $this->slotPayload($slot->fresh('user')),
            ]);
        }

        return back()->with('status', $message);
    }

    public function release(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        if ($slot->user_id !== $request->user()->id) {
            abort(403);
        }

        $slot->loadMissing('song.set.session');
        $slotLabel = Slot::options()[$slot->name] ?? $slot->name;
        $songTitle = $slot->song->artist.' - '.$slot->song->title;
        $setName = $slot->song->set->name;

        $slot->update([
            'user_id' => null,
            'manual_performer_name' => null,
        ]);

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SLOT_DROPPED_FROM_SET,
            app(NotificationService::class)->managersForSet($slot->song->set),
            $request->user(),
            [
                'title' => 'Slot dropped from your set',
                'body' => $request->user()->name.' dropped the '.$slotLabel.' slot on '.$songTitle.' in '.$setName.'.',
                'action_url' => route('sessions.show', $slot->song->set->session).'#slot-'.$slot->id,
                'action_label' => 'Open slot',
            ]
        );

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
        $slot->loadMissing('user', 'song.set');
        $attendanceService = app(JamSessionAttendanceService::class);

        $assignedUserIsNotGoing = $slot->user !== null
            && $attendanceService->isNotGoing($slot->song->set->session, $slot->user);

        return [
            'id' => $slot->id,
            'name' => $slot->name,
            'label' => Slot::options()[$slot->name] ?? $slot->name,
            'notes' => $slot->notes,
            'user_id' => $slot->user_id,
            'user_name' => $slot->assignedPerformerName(),
            'manual_performer_name' => $slot->manual_performer_name,
            'is_open' => $slot->isOpen(),
            'is_claimable' => $attendanceService->slotIsClaimable($slot),
            'is_claimable_manual' => (bool) $slot->is_claimable_manual,
            'assigned_user_not_going' => $assignedUserIsNotGoing,
        ];
    }
}
