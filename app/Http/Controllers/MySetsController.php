<?php

namespace App\Http\Controllers;

use App\Models\Set;
use App\Models\User;
use App\Services\DashboardActionQueueService;
use App\Services\DeezerArtworkLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MySetsController extends Controller
{
    public static function pendingApprovalCount(User $user): int
    {
        return app(DashboardActionQueueService::class)->pendingApprovalCount($user);
    }

    public function __invoke(Request $request, DeezerArtworkLookupService $artworkLookupService): View
    {
        $user = $request->user();

        $ownedOrCollaboratingSetIds = Set::query()
            ->forSetLibrary($user)
            ->visibleTo($user)
            ->pluck('id');

        $performedSets = Set::query()
            ->where('performed', true)
            ->visibleTo($user)
            ->with([
                'owner:id,name',
                'session:id,name,date,is_closed',
                'songs:id,set_id,artist,title,position',
                'songs.slots:id,song_id,user_id',
            ])
            ->withCount('songs')
            ->get();

        $slotSetIds = Set::query()
            ->where('performed', false)
            ->visibleTo($user)
            ->whereHas('songs.slots', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');

        $upcomingSetIds = $ownedOrCollaboratingSetIds
            ->merge($slotSetIds)
            ->unique()
            ->values();

        $upcomingSets = Set::query()
            ->where('performed', false)
            ->visibleTo($user)
            ->whereIn('id', $upcomingSetIds)
            ->with([
                'owner:id,name',
                'session:id,name,date,is_closed',
                'songs:id,set_id,artist,title,position',
                'songs.slots:id,song_id,user_id',
            ])
            ->withCount('songs')
            ->get();

        $sets = $upcomingSets->merge($performedSets)->unique('id')->values();

        $collaboratorIds = $sets
            ->flatMap(fn (Set $set) => $set->collaboratorUserIds())
            ->unique()
            ->values();

        $collaboratorNamesById = User::query()
            ->whereIn('id', $collaboratorIds)
            ->pluck('name', 'id');

        $formatSetCard = function (Set $set) use ($user, $collaboratorNamesById, $artworkLookupService): array {
            $lifecycle = $set->lifecycle_state
                ?? ($set->performed
                    ? Set::LIFECYCLE_PERFORMED
                    : ($set->jam_session_id ? Set::LIFECYCLE_SCHEDULED : Set::LIFECYCLE_DRAFT));

            $hasMySlots = $set->songs
                ->contains(fn ($song) => $song->slots->contains('user_id', $user->id));

            return [
                'set' => $set,
                'lifecycle' => $lifecycle,
                'isOwned' => (int) $set->owner_id === (int) $user->id,
                'isCollaborator' => $set->isCollaborator($user),
                'hasMySlots' => $hasMySlots,
                'artworkTiles' => $artworkLookupService->artworkTilesForSet($set),
                'collaboratorNames' => collect($set->collaboratorUserIds())
                    ->map(fn (int $id): string => (string) ($collaboratorNamesById[$id] ?? 'Unknown'))
                    ->values(),
            ];
        };

        $upcomingCards = $upcomingSets
            ->sortBy(fn (Set $set): string => sprintf(
                '%s|%s',
                $set->session?->date?->format('Ymd') ?? '00000000',
                mb_strtolower($set->name)
            ))
            ->values()
            ->map($formatSetCard)
            ->values();

        $performedCards = $performedSets
            ->sortBy(fn (Set $set): string => sprintf(
                '%s|%s',
                $set->session?->date?->format('Ymd') ?? '99999999',
                mb_strtolower($set->name)
            ))
            ->values()
            ->map($formatSetCard)
            ->values();

        $upcomingPlanned = $upcomingCards
            ->where(fn (array $card): bool => $card['set']->session === null)
            ->values();

        $upcomingSessionGroups = $upcomingCards
            ->where(fn (array $card): bool => $card['set']->session !== null)
            ->groupBy(fn (array $card): int => $card['set']->session->id)
            ->map(function (Collection $cards): array {
                $session = $cards->first()['set']->session;

                return [
                    'session' => $session,
                    'sets' => $cards->values(),
                ];
            })
            ->sortBy(fn (array $group): string => $group['session']->date?->format('Ymd') ?? '99999999')
            ->values();

        $performedSessionGroups = $performedCards
            ->groupBy(fn (array $card): string => (string) ($card['set']->session?->id ?? 'no-session'))
            ->map(function (Collection $cards): array {
                $session = $cards->first()['set']->session;

                return [
                    'session' => $session,
                    'sets' => $cards->values(),
                ];
            })
            ->sortBy(fn (array $group): string => $group['session']?->date?->format('Ymd') ?? '99999999')
            ->values();

        return view('my-sets', [
            'upcomingPlanned' => $upcomingPlanned,
            'upcomingSessionGroups' => $upcomingSessionGroups,
            'performedSessionGroups' => $performedSessionGroups,
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => self::pendingApprovalCount($request->user()),
        ]);
    }
}
