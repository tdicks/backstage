<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\SongRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MySetsController extends Controller
{
    public static function pendingApprovalCount(User $user): int
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

        return $pendingSlotProposalCount + $pendingSongRequestCount + SlotAssignment::query()
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot.song.set', function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereJsonContains('collaborator_ids', $user->id);
            })
            ->count();
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $ownedSets = Set::query()
            ->where('owner_id', $user->id)
            ->visibleTo($user)
            ->with(['session', 'songs.slots.user'])
            ->get();

        $signedSlots = $user->slots()
            ->with(['song.slots.user', 'song.set.session'])
            ->get();

        $signedSetIds = $signedSlots
            ->map(fn (Slot $slot) => $slot->song->set->id)
            ->unique()
            ->values();

        $signedSets = Set::query()
            ->whereIn('id', $signedSetIds)
            ->visibleTo($user)
            ->with(['session', 'songs.slots.user'])
            ->get();

        $sets = $ownedSets
            ->merge($signedSets)
            ->unique('id')
            ->filter(fn (Set $set) => ! $set->performed && ! $set->session->is_hidden && ! $set->session->is_closed)
            ->sortBy(fn (Set $set) => sprintf(
                '%d-%010d-%s',
                $set->performed ? 1 : 0,
                $set->session->date->timestamp,
                $set->name
            ))
            ->values();

        $practiceSets = $sets->map(function (Set $set) use ($user): array {
            $isOwned = $set->owner_id === $user->id;

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
                'isOwned' => $isOwned,
                'songs' => $songs,
            ];
        })->filter(fn (array $group): bool => $group['songs']->isNotEmpty())->values();

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
            ->with(['requester', 'set.session'])
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
            ->groupBy(fn (array $item) => $item['session']->id)
            ->sortBy(fn (Collection $items) => $items->first()['session']->date)
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

        $bandTemplates = BandTemplate::query()
            ->orderBy('name')
            ->get();

        return view('my-sets', [
            'practiceSets' => $practiceSets,
            'pendingForUser' => $pendingForUser,
            'targetConsentApprovals' => $targetConsentApprovals,
            'pendingApprovals' => $pendingApprovals,
            'pendingSongRequests' => $pendingSongRequests,
            'approvalSessions' => $approvalSessions,
            'bandTemplates' => $bandTemplates,
            'slotOptions' => Slot::options(),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => self::pendingApprovalCount($request->user()),
        ]);
    }
}
