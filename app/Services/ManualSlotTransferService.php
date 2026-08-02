<?php

namespace App\Services;

use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Models\User;
use App\Support\NotificationTypeCatalog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManualSlotTransferService
{
    private const SUGGESTED_USER_CACHE_TTL_SECONDS = 1209600;

    public function __construct(
        private readonly JamSessionAttendanceService $attendanceService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @return array<int, array{
     *   slot_id: int,
     *   slot_key: string,
     *   slot_label: string,
     *   manual_performer_name: string,
     *   song_title: string,
     *   song_artist: string,
     *   set_name: string,
     *   session_name: string,
     *   session_date_label: string,
     *   session_url: string,
     *   user_options: array<int, array{id: int, name: string}>
     * }>
     */
    public function dataset(): array
    {
        $slots = $this->eligibleManualSlots();

        if ($slots->isEmpty()) {
            return [];
        }

        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $slotOptions = Slot::options();

        return $slots
            ->map(function (Slot $slot) use ($users, $slotOptions): array {
                $manualName = trim((string) $slot->manual_performer_name);
                $session = $slot->song->set->session;

                return [
                    'slot_id' => (int) $slot->id,
                    'slot_key' => $slot->name,
                    'slot_label' => $slotOptions[$slot->name] ?? $slot->name,
                    'manual_performer_name' => $manualName,
                    'song_title' => $slot->song->title,
                    'song_artist' => $slot->song->artist,
                    'set_name' => $slot->song->set->name,
                    'session_name' => $session->name,
                    'session_date_label' => $session->date->format('M j, Y'),
                    'session_url' => route('sessions.show', $session),
                    'user_options' => $this->rankedUserOptionsForSlot($slot, $manualName, $users),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{slot_id: int, user_id: int|null}>  $changes
     * @return array<int, array{slot_id: int, status: string, message: string, assigned_user_name?: string}>
     */
    public function applyTransfers(array $changes, User $actor): array
    {
        if ($changes === []) {
            return [];
        }

        $eligibleSlotsById = $this->eligibleManualSlots()->keyBy('id');
        $results = [];

        foreach ($changes as $change) {
            $slotId = (int) ($change['slot_id'] ?? 0);
            $userId = isset($change['user_id']) ? (int) $change['user_id'] : null;
            /** @var Slot|null $slot */
            $slot = $eligibleSlotsById->get($slotId);

            if (! $slot) {
                $results[] = [
                    'slot_id' => $slotId,
                    'status' => 'stale',
                    'message' => 'This manual slot is no longer available to transfer.',
                ];

                continue;
            }

            if (! $userId) {
                $results[] = [
                    'slot_id' => $slotId,
                    'status' => 'unchanged',
                    'message' => 'No assignment change was selected for this slot.',
                ];

                continue;
            }

            $targetUser = User::query()->find($userId);

            if (! $targetUser) {
                $results[] = [
                    'slot_id' => $slotId,
                    'status' => 'error',
                    'message' => 'The selected user could not be found.',
                ];

                continue;
            }

            if ($this->attendanceService->isNotGoing($slot->song->set->session, $targetUser)) {
                $this->attendanceService->resetToMaybeForAdminAssignment($slot->song->set->session, $targetUser);
            }

            $conflictingSlot = SlotCompatibility::conflictingSlotForSlot($targetUser->id, $slot, $slot->name);

            if ($conflictingSlot) {
                $slotLabel = Slot::options()[$conflictingSlot->name] ?? $conflictingSlot->name;

                $results[] = [
                    'slot_id' => $slotId,
                    'status' => 'error',
                    'message' => $targetUser->name.' is already assigned to '.$slotLabel.' on this song.',
                ];

                continue;
            }

            $manualName = trim((string) $slot->manual_performer_name);

            DB::transaction(function () use ($slot, $targetUser): void {
                $slot->update([
                    'user_id' => $targetUser->id,
                    'manual_performer_name' => null,
                    'is_claimable_manual' => false,
                ]);

                SlotAssignment::query()
                    ->where('slot_id', $slot->id)
                    ->where('target_user_id', $targetUser->id)
                    ->whereIn('status', [
                        SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
                        SlotAssignment::STATUS_PENDING,
                    ])
                    ->update([
                        'status' => SlotAssignment::STATUS_ACCEPTED,
                        'responded_at' => now(),
                    ]);
            });

            $slot->loadMissing('song.set.session');

            $this->notificationService->notifyUsers(
                NotificationTypeCatalog::SLOT_MANUAL_ASSIGNMENT_TRANSFERRED,
                [$targetUser],
                $actor,
                [
                    'title' => 'Manually-entered slot was transferred to your account',
                    'body' => $actor->name.' assigned the manual entry "'.$manualName.'" ('.(Slot::options()[$slot->name] ?? $slot->name).' on '.$slot->song->artist.' - '.$slot->song->title.') to you.',
                    'action_url' => route('sessions.show', $slot->song->set->session).'#slot-'.$slot->id,
                    'action_label' => 'View slot',
                ]
            );

            $results[] = [
                'slot_id' => $slotId,
                'status' => 'updated',
                'assigned_user_name' => $targetUser->name,
                'message' => 'Transferred to '.$targetUser->name.'.',
            ];
        }

        return $results;
    }

    public function primeMatchesForNewUser(User $user): void
    {
        if ((bool) $user->is_deleted_account) {
            return;
        }

        $manualSlots = $this->eligibleManualSlots();

        foreach ($manualSlots as $slot) {
            $manualName = trim((string) $slot->manual_performer_name);

            if ($this->nameSimilarityScore($manualName, $user->name) <= 0) {
                continue;
            }

            $cacheKey = $this->suggestedUsersCacheKey((int) $slot->id);
            $currentSuggestedUserIds = collect(Cache::get($cacheKey, []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            $updatedUserIds = $currentSuggestedUserIds
                ->push((int) $user->id)
                ->unique()
                ->take(20)
                ->values()
                ->all();

            Cache::put($cacheKey, $updatedUserIds, self::SUGGESTED_USER_CACHE_TTL_SECONDS);
        }
    }

    /**
     * @return EloquentCollection<int, Slot>
     */
    private function eligibleManualSlots(): EloquentCollection
    {
        return Slot::query()
            ->whereNull('user_id')
            ->whereNotNull('manual_performer_name')
            ->where('manual_performer_name', '!=', '')
            ->whereHas('song.set.session', function ($query): void {
                $query
                    ->whereDate('date', '>=', today()->toDateString())
                    ->where('is_closed', false)
                    ->where('is_archived', false);
            })
            ->with([
                'song.set.session:id,name,date,is_closed,is_archived',
                'song.set:id,name,jam_session_id',
                'song:id,set_id,title,artist',
            ])
            ->get()
            ->sort(function (Slot $left, Slot $right): int {
                $leftDate = $left->song->set->session->date?->timestamp ?? PHP_INT_MAX;
                $rightDate = $right->song->set->session->date?->timestamp ?? PHP_INT_MAX;

                if ($leftDate !== $rightDate) {
                    return $leftDate <=> $rightDate;
                }

                $setCompare = strcasecmp($left->song->set->name, $right->song->set->name);

                if ($setCompare !== 0) {
                    return $setCompare;
                }

                $songCompare = strcasecmp($left->song->title, $right->song->title);

                if ($songCompare !== 0) {
                    return $songCompare;
                }

                $positionCompare = ((int) $left->position) <=> ((int) $right->position);

                if ($positionCompare !== 0) {
                    return $positionCompare;
                }

                return ((int) $left->id) <=> ((int) $right->id);
            })
            ->values();
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function rankedUserOptionsForSlot(Slot $slot, string $manualName, EloquentCollection $users): array
    {
        $boostedUserIds = collect(Cache::get($this->suggestedUsersCacheKey((int) $slot->id), []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        return $users
            ->map(function (User $user) use ($manualName, $boostedUserIds): array {
                $score = $this->nameSimilarityScore($manualName, $user->name);

                if (in_array((int) $user->id, $boostedUserIds, true)) {
                    $score += 15;
                }

                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'score' => $score,
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['score'] !== $right['score']) {
                    return $right['score'] <=> $left['score'];
                }

                return strcasecmp($left['name'], $right['name']);
            })
            ->map(fn (array $option): array => [
                'id' => $option['id'],
                'name' => $option['name'],
            ])
            ->values()
            ->all();
    }

    private function nameSimilarityScore(string $manualName, string $userName): int
    {
        $manualNormalized = $this->normalizeName($manualName);
        $userNormalized = $this->normalizeName($userName);

        if ($manualNormalized === '' || $userNormalized === '') {
            return 0;
        }

        $score = 0;

        if ($manualNormalized === $userNormalized) {
            $score += 100;
        }

        if (str_contains($userNormalized, $manualNormalized) || str_contains($manualNormalized, $userNormalized)) {
            $score += 35;
        }

        $manualTokens = $this->nameTokens($manualNormalized);
        $userTokens = $this->nameTokens($userNormalized);

        if ($manualTokens->isEmpty() || $userTokens->isEmpty()) {
            return $score;
        }

        $sharedTokenCount = $manualTokens
            ->intersect($userTokens)
            ->count();

        $score += $sharedTokenCount * 18;

        $leadToken = (string) $manualTokens->first();

        if ($leadToken !== '' && str_starts_with($userNormalized, $leadToken)) {
            $score += 12;
        }

        return $score;
    }

    private function normalizeName(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return (string) preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? '');
    }

    /**
     * @return Collection<int, string>
     */
    private function nameTokens(string $normalizedName): Collection
    {
        return collect(explode(' ', $normalizedName))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '' && mb_strlen($token) >= 2)
            ->values();
    }

    private function suggestedUsersCacheKey(int $slotId): string
    {
        return 'manual-slot-transfer:suggested-users:'.$slotId;
    }
}
