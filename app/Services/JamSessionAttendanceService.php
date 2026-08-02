<?php

namespace App\Services;

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\Song;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Support\Collection;

class JamSessionAttendanceService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public const DROPOUT_KEEP_CLAIMABLE = 'keep_claimable';

    public const DROPOUT_RELEASE_SLOTS = 'release_slots';

    /**
     * @return list<string>
     */
    public static function dropoutActions(): array
    {
        return [
            self::DROPOUT_KEEP_CLAIMABLE,
            self::DROPOUT_RELEASE_SLOTS,
        ];
    }

    public function statusForUser(JamSession $session, User $user): string
    {
        return $session->attendances()
            ->where('user_id', $user->id)
            ->value('status')
            ?? JamSessionAttendance::STATUS_MAYBE;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function statusMapForUsers(JamSession $session, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $session->attendances()
            ->whereIn('user_id', $userIds)
            ->pluck('status', 'user_id')
            ->mapWithKeys(fn ($status, $userId) => [(int) $userId => (string) $status])
            ->all();
    }

    public function isNotGoing(JamSession $session, User $user): bool
    {
        return $this->statusForUser($session, $user) === JamSessionAttendance::STATUS_NOT_GOING;
    }

    public function markGoingIfAllowed(JamSession $session, User $user, string $source): string
    {
        $currentStatus = $this->statusForUser($session, $user);

        if ($currentStatus === JamSessionAttendance::STATUS_NOT_GOING) {
            return $currentStatus;
        }

        $session->attendances()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => JamSessionAttendance::STATUS_GOING,
                'source' => $source,
                'status_changed_at' => now(),
            ]
        );

        return JamSessionAttendance::STATUS_GOING;
    }

    public function setStatus(
        JamSession $session,
        User $user,
        string $status,
        string $source,
        ?string $dropoutAction = null,
    ): string {
        $currentStatus = $this->statusForUser($session, $user);

        if ($status === JamSessionAttendance::STATUS_NOT_GOING && $currentStatus !== JamSessionAttendance::STATUS_NOT_GOING) {
            $this->handleDropoutTransition($session, $user, $dropoutAction ?? self::DROPOUT_KEEP_CLAIMABLE, $source);
        }

        $session->attendances()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'source' => $source,
                'status_changed_at' => now(),
            ]
        );

        return $status;
    }

    public function resetToMaybeForAdminAssignment(JamSession $session, User $user): void
    {
        $session->attendances()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => JamSessionAttendance::STATUS_MAYBE,
                'source' => JamSessionAttendance::SOURCE_ADMIN_ASSIGNMENT,
                'status_changed_at' => now(),
            ]
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{id: string, name: string, attendance_status: string, selectable: bool, attendance_group: string}>
     */
    public function assignmentUserOptions(JamSession $session, Collection $users, User $viewer): Collection
    {
        $statuses = $this->statusMapForUsers($session, $users->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $users->map(function (User $candidate) use ($statuses, $viewer) {
            $status = $statuses[$candidate->id] ?? JamSessionAttendance::STATUS_MAYBE;
            $isNotGoing = $status === JamSessionAttendance::STATUS_NOT_GOING;

            return [
                'id' => (string) $candidate->id,
                'name' => $candidate->name,
                'attendance_status' => $status,
                'selectable' => $viewer->is_admin || ! $isNotGoing,
                'attendance_group' => $isNotGoing ? 'not_attending' : 'available',
            ];
        })->values();
    }

    public function slotIsClaimableDueToDropout(Slot $slot): bool
    {
        if ($slot->user_id === null) {
            return false;
        }

        return JamSessionAttendance::query()
            ->where('jam_session_id', $slot->song->set->jam_session_id)
            ->where('user_id', $slot->user_id)
            ->where('status', JamSessionAttendance::STATUS_NOT_GOING)
            ->exists();
    }

    public function slotIsManuallyClaimable(Slot $slot): bool
    {
        return $slot->user_id !== null && (bool) $slot->is_claimable_manual;
    }

    public function slotIsClaimable(Slot $slot): bool
    {
        return $this->slotIsManuallyClaimable($slot)
            || $this->slotIsClaimableDueToDropout($slot);
    }

    public function userRequiresDropoutActionPrompt(JamSession $session, User $user): bool
    {
        $hasSets = Set::query()
            ->where('jam_session_id', $session->id)
            ->where(function ($query) use ($user): void {
                $query
                    ->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->exists();

        if ($hasSets) {
            return true;
        }

        return Slot::query()
            ->whereHas('song.set', fn ($query) => $query->where('jam_session_id', $session->id))
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @return Collection<int, array{id: string, name: string, status: string}>
     */
    public function usersForAttendanceModal(JamSession $session): Collection
    {
        return $session->attendances()
            ->with('user')
            ->whereIn('status', [
                JamSessionAttendance::STATUS_GOING,
                JamSessionAttendance::STATUS_NOT_GOING,
            ])
            ->get()
            ->filter(fn (JamSessionAttendance $attendance) => $attendance->user !== null)
            ->sortBy(fn (JamSessionAttendance $attendance) => mb_strtolower($attendance->user->name))
            ->map(fn (JamSessionAttendance $attendance) => [
                'id' => (string) $attendance->user->id,
                'name' => $attendance->user->name,
                'status' => $attendance->status,
            ])
            ->values();
    }

    private function handleDropoutTransition(JamSession $session, User $user, string $dropoutAction, string $source): void
    {
        $dropoutImpact = $this->dropoutImpactPayload($session, $user, $dropoutAction, $source);

        SlotAssignment::query()
            ->whereHas('slot.song.set', fn ($query) => $query->where('jam_session_id', $session->id))
            ->where('target_user_id', $user->id)
            ->whereIn('status', [
                SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
                SlotAssignment::STATUS_PENDING,
            ])
            ->update([
                'status' => SlotAssignment::STATUS_REJECTED,
                'responded_at' => now(),
            ]);

        if ($dropoutAction === self::DROPOUT_RELEASE_SLOTS) {
            Slot::query()
                ->whereHas('song.set', fn ($query) => $query->where('jam_session_id', $session->id))
                ->where('user_id', $user->id)
                ->update([
                    'user_id' => null,
                    'manual_performer_name' => null,
                ]);
        }

        if ($dropoutImpact === null) {
            return;
        }

        $this->notificationService->notifyUsers(
            NotificationTypeCatalog::SLOT_DROPPED_FROM_SET,
            $dropoutImpact['recipients'],
            $user,
            $dropoutImpact['content'],
        );
    }

    /**
     * @return array{recipients: Collection<int, User>, content: array{title: string, body: string, action_url: string|null, action_label: string}}|null
     */
    private function dropoutImpactPayload(JamSession $session, User $user, string $dropoutAction, string $source): ?array
    {
        if ($source !== JamSessionAttendance::SOURCE_SELF) {
            return null;
        }

        $affectedSlots = Slot::query()
            ->whereHas('song.set', fn ($query) => $query->where('jam_session_id', $session->id))
            ->where('user_id', $user->id)
            ->with('song.set')
            ->get();

        if ($affectedSlots->isEmpty()) {
            return null;
        }

        $affectedSetIds = $affectedSlots
            ->pluck('song.set.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $affectedSets = Set::query()
            ->whereIn('id', $affectedSetIds->all())
            ->with('songs.slots')
            ->get();

        $managerIds = $affectedSets
            ->flatMap(function (Set $set): array {
                return [$set->owner_id, ...$set->collaboratorUserIds()];
            })
            ->map(fn ($id) => (int) $id)
            ->unique();

        $fellowPerformerIds = $affectedSets
            ->flatMap(fn (Set $set) => $set->songs)
            ->flatMap(fn ($song) => $song->slots)
            ->pluck('user_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== (int) $user->id)
            ->unique();

        $adminIds = User::query()
            ->where('is_admin', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $recipientIds = collect([$managerIds, $fellowPerformerIds, $adminIds])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== (int) $user->id)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return null;
        }

        $slotOptions = Slot::options();
        $setSummaries = $affectedSlots
            ->groupBy(fn (Slot $slot) => (int) $slot->song->set->id)
            ->map(function (Collection $setSlots) use ($slotOptions): string {
                /** @var Set $set */
                $set = $setSlots->first()->song->set;

                $songSummaries = $setSlots
                    ->groupBy(fn (Slot $slot) => (int) $slot->song_id)
                    ->map(function (Collection $songSlots) use ($slotOptions): string {
                        /** @var Song $song */
                        $song = $songSlots->first()->song;
                        $slotLabels = $songSlots
                            ->map(fn (Slot $slot) => $slotOptions[$slot->name] ?? str($slot->name)->replace('_', ' ')->title()->toString())
                            ->unique()
                            ->values()
                            ->implode(', ');

                        return $song->artist.' - '.$song->title.' ('.$slotLabels.')';
                    })
                    ->values()
                    ->implode('; ');

                return $set->name.': '.$songSummaries;
            })
            ->values();

        $setDetail = $setSummaries->take(3)->implode(' | ');
        if ($setSummaries->count() > 3) {
            $setDetail .= ' | +'.($setSummaries->count() - 3).' more set(s)';
        }

        $slotCount = $affectedSlots->count();
        $slotCountText = $slotCount === 1 ? '1 slot' : $slotCount.' slots';
        $slotOutcome = $dropoutAction === self::DROPOUT_RELEASE_SLOTS
            ? 'They chose to release their '.$slotCountText.'.'
            : 'Their '.$slotCountText.' remain assigned but are now claimable.';

        $body = $user->name.' marked not going for '.$session->name.'. '.$slotOutcome.' Affected sets: '.$setDetail.'.';

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->orderBy('name')
            ->get();

        return [
            'recipients' => $recipients,
            'content' => [
                'title' => $user->name.' dropped out of '.$session->name,
                'body' => $body,
                'action_url' => route('sessions.show', $session),
                'action_label' => 'Open session',
            ],
        ];
    }
}
