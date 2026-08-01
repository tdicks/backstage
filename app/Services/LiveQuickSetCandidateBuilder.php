<?php

namespace App\Services;

use App\Models\JamStandardSong;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LiveQuickSetCandidateBuilder
{
    /**
     * @param  Collection<int, User>  $checkedInUsers
     * @param  array<string, list<string>>  $slotConflicts
     * @return Collection<int, array<string, mixed>>
     */
    public function build(Collection $checkedInUsers, array $slotConflicts): Collection
    {
        $checkedInUserIds = $checkedInUsers->pluck('id');
        $globalCapableSlotNames = $checkedInUsers
            ->flatMap(fn (User $user) => collect($user->slotCoverageMap())
                ->filter(fn (string $state) => $state === User::SLOT_COVERAGE_CAN)
                ->keys())
            ->unique()
            ->values();

        if ($checkedInUserIds->isEmpty()) {
            return collect();
        }

        return JamStandardSong::query()
            ->active()
            ->where(function (Builder $query) use ($checkedInUserIds, $globalCapableSlotNames): void {
                $query->whereHas('userSlots', fn (Builder $userSlotsQuery) => $userSlotsQuery->whereIn('user_id', $checkedInUserIds));

                if ($globalCapableSlotNames->isNotEmpty()) {
                    $query->orWhereHas('slots', fn (Builder $slotsQuery) => $slotsQuery->whereIn('name', $globalCapableSlotNames));
                }
            })
            ->with([
                'slots',
                'userSlots' => fn ($query) => $query->whereIn('user_id', $checkedInUserIds),
            ])
            ->get()
            ->map(function (JamStandardSong $song) use ($checkedInUsers, $slotConflicts): ?array {
                $candidateUserIdsBySlot = [];
                $confirmedUserIdsBySlot = [];
                $confirmedUserIds = $song->userSlots
                    ->groupBy('slot_name')
                    ->map(fn (Collection $slots) => $slots->pluck('user_id')->map(fn (int $userId) => (string) $userId)->all());

                foreach ($song->slots as $slot) {
                    $slotName = $slot->name;
                    $candidateUserIdsBySlot[$slotName] = [];
                    $confirmedUserIdsBySlot[$slotName] = [];

                    foreach ($checkedInUsers as $user) {
                        if ($user->willNotCoverSlot($slotName)) {
                            continue;
                        }

                        $isConfirmed = in_array((string) $user->id, $confirmedUserIds[$slotName] ?? [], true);

                        if (! $isConfirmed && ! $user->coversSlot($slotName)) {
                            continue;
                        }

                        $candidateUserIdsBySlot[$slotName][] = $user->id;

                        if ($isConfirmed) {
                            $confirmedUserIdsBySlot[$slotName][] = $user->id;
                        }
                    }
                }

                $coverage = $this->bestCoverage(
                    $song->slots->pluck('name')->all(),
                    $candidateUserIdsBySlot,
                    $confirmedUserIdsBySlot,
                    $slotConflicts,
                );

                if ($coverage['covered_slot_count'] === 0) {
                    return null;
                }

                return [
                    'id' => $song->id,
                    'artist' => $song->artist,
                    'title' => $song->title,
                    'duration' => $song->duration,
                    'source' => $song->source,
                    'slots' => $song->slots->map(fn ($slot) => ['name' => $slot->name])->values()->all(),
                    'capable_user_ids' => $candidateUserIdsBySlot,
                    'confirmed_user_ids' => $confirmedUserIdsBySlot,
                    ...$coverage,
                ];
            })
            ->filter()
            ->sortBy([
                ['confirmed_assignment_count', 'desc'],
                ['covered_slot_count', 'desc'],
                ['artist', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  list<string>  $slotNames
     * @param  array<string, list<int>>  $candidateUserIdsBySlot
     * @param  array<string, list<int>>  $confirmedUserIdsBySlot
     * @param  array<string, list<string>>  $slotConflicts
     * @return array{covered_slot_count: int, confirmed_assignment_count: int, suggested_assignments: array<string, int>}
     */
    private function bestCoverage(array $slotNames, array $candidateUserIdsBySlot, array $confirmedUserIdsBySlot, array $slotConflicts): array
    {
        usort($slotNames, fn (string $left, string $right): int => count($candidateUserIdsBySlot[$left] ?? []) <=> count($candidateUserIdsBySlot[$right] ?? []));

        $best = [
            'covered_slot_count' => 0,
            'confirmed_assignment_count' => 0,
            'suggested_assignments' => [],
        ];

        $search = function (int $slotIndex, array $assignments, array $assignedSlotsByUser, int $confirmedAssignmentCount) use (&$search, &$best, $slotNames, $candidateUserIdsBySlot, $confirmedUserIdsBySlot, $slotConflicts): void {
            $remainingSlots = count($slotNames) - $slotIndex;

            if ($confirmedAssignmentCount + $remainingSlots < $best['confirmed_assignment_count']) {
                return;
            }

            if ($slotIndex === count($slotNames)) {
                $coveredSlotCount = count($assignments);

                if ($confirmedAssignmentCount > $best['confirmed_assignment_count'] || ($confirmedAssignmentCount === $best['confirmed_assignment_count'] && $coveredSlotCount > $best['covered_slot_count'])) {
                    $best = [
                        'covered_slot_count' => $coveredSlotCount,
                        'confirmed_assignment_count' => $confirmedAssignmentCount,
                        'suggested_assignments' => $assignments,
                    ];
                }

                return;
            }

            $slotName = $slotNames[$slotIndex];

            foreach ($candidateUserIdsBySlot[$slotName] ?? [] as $userId) {
                $assignedSlots = $assignedSlotsByUser[$userId] ?? [];

                if ($this->hasSlotConflict($slotName, $assignedSlots, $slotConflicts)) {
                    continue;
                }

                $nextAssignments = [...$assignments, $slotName => $userId];
                $nextAssignedSlotsByUser = $assignedSlotsByUser;
                $nextAssignedSlotsByUser[$userId] = [...$assignedSlots, $slotName];
                $isConfirmed = in_array($userId, $confirmedUserIdsBySlot[$slotName] ?? [], true);

                $search($slotIndex + 1, $nextAssignments, $nextAssignedSlotsByUser, $confirmedAssignmentCount + (int) $isConfirmed);
            }

            $search($slotIndex + 1, $assignments, $assignedSlotsByUser, $confirmedAssignmentCount);
        };

        $search(0, [], [], 0);

        return $best;
    }

    /**
     * @param  list<string>  $assignedSlots
     * @param  array<string, list<string>>  $slotConflicts
     */
    private function hasSlotConflict(string $slotName, array $assignedSlots, array $slotConflicts): bool
    {
        foreach ($assignedSlots as $assignedSlotName) {
            if (in_array($assignedSlotName, $slotConflicts[$slotName] ?? [], true) || in_array($slotName, $slotConflicts[$assignedSlotName] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
