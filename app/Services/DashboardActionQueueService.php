<?php

namespace App\Services;

use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardActionQueueService
{
    public function pendingApprovalCount(User $user): int
    {
        $pendingSlotProposalCount = SlotAssignment::query()
            ->where('type', SlotAssignment::TYPE_PROPOSAL)
            ->where('status', SlotAssignment::STATUS_AWAITING_TARGET_CONSENT)
            ->where('target_user_id', $user->id)
            ->count();

        $pendingSongRequestCount = SongRequest::query()
            ->where('status', SongRequest::STATUS_PENDING)
            ->whereHas('set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->count();

        $pendingSlotAssignmentCount = SlotAssignment::query()
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot.song.set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->count();

        return $pendingSlotProposalCount + $pendingSongRequestCount + $pendingSlotAssignmentCount;
    }

    /**
     * @return array{
     *   pending_for_user: Collection<int, SlotAssignment>,
     *   target_consent_approvals: Collection<int, SlotAssignment>,
     *   pending_approvals: Collection<int, array{song: mixed, set: mixed, session: mixed, assignments: Collection<int, SlotAssignment>}>,
     *   pending_song_requests: Collection<int, SongRequest>,
     *   approval_sessions: Collection<int, array{session: mixed, sets: Collection<int, array{set: mixed, songs: Collection<int, array{song: mixed, items: Collection<int, array{session: mixed, set: mixed, song: mixed, type: string, approval: mixed}>}>, songRequests: Collection<int, array{session: mixed, set: mixed, song: mixed, type: string, approval: mixed}>}>}>,
     *   approvals_total: int
     * }
     */
    public function queuesForUser(User $user): array
    {
        $pendingForUser = SlotAssignment::query()
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->where(function ($query) use ($user): void {
                $query->where('actor_user_id', $user->id)
                    ->orWhere('target_user_id', $user->id);
            })
            ->whereDoesntHave('slot.song.set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->whereHas('slot.song.set.session', function ($query): void {
                $query->where('is_hidden', false)
                    ->where('is_closed', false);
            })
            ->with(['actor', 'target', 'slot.song.set.session'])
            ->orderByDesc('created_at')
            ->get();

        $targetConsentApprovals = SlotAssignment::query()
            ->where('type', SlotAssignment::TYPE_PROPOSAL)
            ->where('status', SlotAssignment::STATUS_AWAITING_TARGET_CONSENT)
            ->where('target_user_id', $user->id)
            ->with(['actor', 'target', 'slot.song.set.session'])
            ->orderByDesc('created_at')
            ->get();

        $pendingApprovals = SlotAssignment::query()
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot.song.set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->with(['actor', 'target', 'slot.song.set.session'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (SlotAssignment $assignment) => $assignment->slot->song->id)
            ->map(function (Collection $assignments): array {
                $first = $assignments->first();

                return [
                    'song' => $first->slot->song,
                    'set' => $first->slot->song->set,
                    'session' => $first->slot->song->set->session,
                    'assignments' => $assignments->values(),
                ];
            })
            ->values();

        $pendingSongRequests = SongRequest::query()
            ->where('status', SongRequest::STATUS_PENDING)
            ->whereHas('set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->with(['requester', 'set.session', 'set.owner'])
            ->orderByDesc('created_at')
            ->get();

        $approvalSessions = $targetConsentApprovals
            ->map(fn (SlotAssignment $approval): array => [
                'session' => $approval->slot->song->set->session,
                'set' => $approval->slot->song->set,
                'song' => $approval->slot->song,
                'type' => 'target_consent',
                'approval' => $approval,
            ])
            ->toBase()
            ->merge($pendingApprovals->flatMap(fn (array $group) => $group['assignments']->map(fn (SlotAssignment $approval): array => [
                'session' => $group['session'],
                'set' => $group['set'],
                'song' => $group['song'],
                'type' => 'set_assignment',
                'approval' => $approval,
            ])))
            ->merge($pendingSongRequests->map(fn (SongRequest $songRequest): array => [
                'session' => $songRequest->set->session,
                'set' => $songRequest->set,
                'song' => null,
                'type' => 'song_request',
                'approval' => $songRequest,
            ]))
            ->groupBy(fn (array $item) => $item['session'] ? 'session:'.$item['session']->id : 'planned')
            ->sortBy(fn (Collection $items) => $items->first()['session']?->date?->timestamp ?? PHP_INT_MAX)
            ->map(function (Collection $sessionItems): array {
                $session = $sessionItems->first()['session'];

                return [
                    'session' => $session,
                    'sets' => $sessionItems
                        ->groupBy(fn (array $item) => $item['set']->id)
                        ->sortBy(fn (Collection $items) => $items->first()['set']->position)
                        ->map(function (Collection $setItems): array {
                            $set = $setItems->first()['set'];

                            return [
                                'set' => $set,
                                'songs' => $setItems
                                    ->filter(fn (array $item) => $item['song'] !== null)
                                    ->groupBy(fn (array $item) => $item['song']->id)
                                    ->sortBy(fn (Collection $items) => $items->first()['song']->position)
                                    ->map(fn (Collection $songItems): array => [
                                        'song' => $songItems->first()['song'],
                                        'items' => $songItems->values(),
                                    ])
                                    ->values(),
                                'songRequests' => $setItems
                                    ->filter(fn (array $item) => $item['type'] === 'song_request')
                                    ->sortBy(fn (array $item) => $item['approval']->created_at)
                                    ->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return [
            'pending_for_user' => $pendingForUser,
            'target_consent_approvals' => $targetConsentApprovals,
            'pending_approvals' => $pendingApprovals,
            'pending_song_requests' => $pendingSongRequests,
            'approval_sessions' => $approvalSessions,
            'approvals_total' => $targetConsentApprovals->count()
                + $pendingApprovals->sum(fn (array $group) => $group['assignments']->count())
                + $pendingSongRequests->count(),
        ];
    }

    /**
     * @return Collection<int, array{set: Set, isOwned: bool, isCollaborator: bool, songs: Collection<int, array{song: mixed, mySlots: Collection<int, Slot>, slots: Collection<int, Slot>}>}>
     */
    public function practiceSetsForUser(User $user): Collection
    {
        $sets = Set::query()
            ->visibleTo($user)
            ->where('performed', false)
            ->whereHas('session', function ($query): void {
                $query->where('is_hidden', false)
                    ->where('is_closed', false);
            })
            ->whereHas('songs.slots', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('attachments')
            ->with([
                'owner',
                'session',
                'songs' => fn ($songQuery) => $songQuery
                    ->withCount('attachments')
                    ->with([
                        'slots' => fn ($slotQuery) => $slotQuery
                            ->with('user')
                            ->withCount('attachments'),
                    ]),
            ])
            ->orderBy('jam_session_id')
            ->orderBy('position')
            ->get();

        return $sets->map(function (Set $set) use ($user): array {
            $songs = $set->songs
                ->filter(fn ($song) => $song->slots->contains('user_id', $user->id))
                ->map(function ($song) use ($user): array {
                    $mySlots = $song->slots->where('user_id', $user->id)->values();

                    return [
                        'song' => $song,
                        'mySlots' => $mySlots,
                        'slots' => $mySlots,
                    ];
                })
                ->values();

            return [
                'set' => $set,
                'isOwned' => $set->owner_id === $user->id,
                'isCollaborator' => $set->isCollaborator($user),
                'songs' => $songs,
            ];
        })->filter(fn (array $group): bool => $group['songs']->isNotEmpty())->values();
    }
}
