<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecycleBinController extends Controller
{
    public function index(Request $request): View
    {
        return view('recycle-bin.index', [
            'initialCount' => self::countForUser($request->user()),
            'listUrl' => route('recycle-bin.items'),
            'restoreSetUrlTemplate' => route('recycle-bin.sets.restore', '__SET_ID__'),
            'restoreSessionUrlTemplate' => route('recycle-bin.sessions.restore', '__SESSION_ID__'),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => self::countForUser($request->user()),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $user = $request->user();

        $sets = Set::onlyTrashed()
            ->with([
                'owner:id,name',
                'session' => fn ($query) => $query
                    ->withTrashed()
                    ->select('id', 'name', 'date', 'deleted_at'),
                'songs.slots.user:id,name',
            ])
            ->when(! $user->is_admin, fn ($query) => $query->where('deleted_by_user_id', $user->id))
            ->latest('deleted_at')
            ->get();

        $sessions = JamSession::onlyTrashed()
            ->when(! $user->is_admin, fn ($query) => $query->whereRaw('0 = 1'))
            ->latest('deleted_at')
            ->get();

        $deletedSetsBySessionId = $sets
            ->filter(fn (Set $set) => $set->session?->trashed())
            ->groupBy('jam_session_id');

        return response()->json([
            'count' => $sets->count() + $sessions->count(),
            'restore_session_options' => $this->restoreSessionOptions($user)->values(),
            'sets' => $sets->map(fn (Set $set) => $this->setPayload($set))->values(),
            'sessions' => $sessions->map(fn (JamSession $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'date' => $session->date?->format('M j, Y'),
                'deleted_at' => optional($session->deleted_at)?->toIso8601String(),
                'deleted_ago' => optional($session->deleted_at)?->diffForHumans(),
                'deleted_sets' => ($deletedSetsBySessionId->get($session->id) ?? collect())
                    ->map(fn (Set $set) => $this->deletedSessionSetSummaryPayload($set))
                    ->values(),
                'overview' => [
                    'sets' => ($deletedSetsBySessionId->get($session->id) ?? collect())
                        ->map(fn (Set $set) => $this->sessionOverviewSetPayload($set))
                        ->values(),
                ],
            ])->values(),
        ]);
    }

    public function restoreSet(Request $request, int $setId): JsonResponse
    {
        $set = Set::onlyTrashed()->findOrFail($setId);
        $this->authorize('restore', $set);

        $validated = $request->validate([
            'restore_as_hidden' => ['nullable', 'boolean'],
            'clear_slot_assignments' => ['nullable', 'boolean'],
            'jam_session_id' => ['nullable', 'integer'],
        ]);

        $targetSessionId = isset($validated['jam_session_id'])
            ? (int) $validated['jam_session_id']
            : null;

        if ($targetSessionId === null && $set->jam_session_id) {
            $existingSession = JamSession::query()->find($set->jam_session_id);

            if ($existingSession) {
                $targetSessionId = $existingSession->id;
            }
        }

        if ($targetSessionId === null) {
            return response()->json([
                'message' => 'Choose a jam session to restore this set to.',
            ], 422);
        }

        $targetSession = $this->restoreTargetSessionQuery($request->user())
            ->find($targetSessionId);

        if (! $targetSession) {
            return response()->json([
                'message' => 'Choose a valid jam session to restore this set to.',
            ], 422);
        }

        if (! $request->user()->is_admin && $targetSession->is_closed) {
            return response()->json([
                'message' => 'Sets can only be restored to open jam sessions.',
            ], 422);
        }

        $restoreAsHidden = (bool) ($validated['restore_as_hidden'] ?? false);
        $clearSlotAssignments = (bool) ($validated['clear_slot_assignments'] ?? false);

        $set->restore();
        $set->jam_session_id = $targetSession->id;
        $set->is_hidden = $restoreAsHidden;
        $set->deleted_by_user_id = null;
        $set->save();

        if ($clearSlotAssignments) {
            Slot::query()
                ->whereHas('song', fn ($query) => $query->where('set_id', $set->id))
                ->update([
                    'user_id' => null,
                    'manual_performer_name' => null,
                    'is_claimable_manual' => false,
                ]);
        }

        return response()->json([
            'message' => 'Set restored.',
            'count' => self::countForUser($request->user()),
        ]);
    }

    public function restoreSession(Request $request, int $sessionId): JsonResponse
    {
        $session = JamSession::onlyTrashed()->findOrFail($sessionId);
        $this->authorize('restore', $session);

        $validated = $request->validate([
            'restore_as_hidden' => ['nullable', 'boolean'],
            'selected_set_ids' => ['nullable', 'array'],
            'selected_set_ids.*' => ['integer'],
        ]);

        $deletedSets = Set::onlyTrashed()
            ->where('jam_session_id', $session->id)
            ->get();

        $selectedSetIds = $request->has('selected_set_ids')
            ? collect($validated['selected_set_ids'] ?? [])->map(fn ($id) => (int) $id)->values()
            : $deletedSets->pluck('id')->map(fn ($id) => (int) $id)->values();

        $restorableSetIds = $deletedSets
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->intersect($selectedSetIds)
            ->values();

        $restoreAsHidden = (bool) ($validated['restore_as_hidden'] ?? false);

        $session->restore();
        $session->is_hidden = $restoreAsHidden;
        $session->deleted_by_user_id = null;
        $session->save();

        if ($restorableSetIds->isNotEmpty()) {
            Set::onlyTrashed()
                ->whereIn('id', $restorableSetIds)
                ->restore();

            Set::query()
                ->whereIn('id', $restorableSetIds)
                ->update(['deleted_by_user_id' => null]);
        }

        return response()->json([
            'message' => 'Jam session restored.',
            'count' => self::countForUser($request->user()),
        ]);
    }

    public static function countForUser($user): int
    {
        if (! $user) {
            return 0;
        }

        $setsQuery = Set::onlyTrashed();

        if (! $user->is_admin) {
            $setsQuery->where('deleted_by_user_id', $user->id);
        }

        $setsCount = (int) $setsQuery->count();
        $sessionsCount = $user->is_admin ? (int) JamSession::onlyTrashed()->count() : 0;

        return $setsCount + $sessionsCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function setPayload(Set $set): array
    {
        return [
            'id' => $set->id,
            'name' => $set->name,
            'session_id' => $set->jam_session_id,
            'owner_name' => $set->owner?->name,
            'session_name' => $set->session?->name,
            'session_date' => $set->session?->date?->format('M j, Y'),
            'session_deleted' => (bool) $set->session?->trashed(),
            'deleted_at' => optional($set->deleted_at)?->toIso8601String(),
            'deleted_ago' => optional($set->deleted_at)?->diffForHumans(),
            'overview' => [
                'settings' => [
                    ['label' => 'Hidden', 'enabled' => (bool) $set->is_hidden],
                    ['label' => 'Free for all', 'enabled' => (bool) $set->free_for_all],
                    ['label' => 'Sign ups open', 'enabled' => (bool) $set->signups_open],
                    ['label' => 'Song requests', 'enabled' => (bool) $set->song_requests],
                    ['label' => 'Performed', 'enabled' => (bool) $set->performed],
                    ['label' => 'Feature set', 'enabled' => (bool) $set->feature_set],
                ],
                'songs' => $set->songs
                    ->map(fn (Song $song) => $this->songPayload($song))
                    ->values(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deletedSessionSetSummaryPayload(Set $set): array
    {
        return [
            'id' => $set->id,
            'name' => $set->name,
            'owner_name' => $set->owner?->name,
            'deleted_ago' => optional($set->deleted_at)?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionOverviewSetPayload(Set $set): array
    {
        return [
            'id' => $set->id,
            'name' => $set->name,
            'owner_name' => $set->owner?->name,
            'songs' => $set->songs
                ->map(fn (Song $song) => [
                    'id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist,
                ])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function songPayload(Song $song): array
    {
        return [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist,
            'slots' => $song->slots
                ->map(fn (Slot $slot) => [
                    'id' => $slot->id,
                    'label' => Slot::options()[$slot->name] ?? $slot->name,
                    'performer_name' => $slot->user?->name ?? $slot->manual_performer_name ?? 'Open',
                ])
                ->values(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, date_label: string, label: string, disabled: bool}>
     */
    private function restoreSessionOptions($user): Collection
    {
        return $this->restoreTargetSessionQuery($user)
            ->orderByDesc('date')
            ->get(['id', 'name', 'date', 'is_closed'])
            ->map(fn (JamSession $session) => [
                'id' => (int) $session->id,
                'name' => $session->name,
                'date_label' => $session->date?->format('M j, Y') ?? '',
                'label' => $session->name.' ('.($session->date?->format('M j, Y') ?? '').')'.($session->is_closed ? ' (Closed)' : ''),
                'disabled' => ! $user->is_admin && (bool) $session->is_closed,
            ]);
    }

    private function restoreTargetSessionQuery($user)
    {
        return JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->when(! $user->is_admin, fn ($query) => $query->whereDate('date', '>=', today()->toDateString()));
    }
}
