<?php

namespace App\Services;

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

class JamSessionAttendanceService
{
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
            $this->handleDropoutTransition($session, $user, $dropoutAction ?? self::DROPOUT_KEEP_CLAIMABLE);
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

    private function handleDropoutTransition(JamSession $session, User $user, string $dropoutAction): void
    {
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

        if ($dropoutAction !== self::DROPOUT_RELEASE_SLOTS) {
            return;
        }

        Slot::query()
            ->whereHas('song.set', fn ($query) => $query->where('jam_session_id', $session->id))
            ->where('user_id', $user->id)
            ->update([
                'user_id' => null,
                'manual_performer_name' => null,
            ]);
    }
}
