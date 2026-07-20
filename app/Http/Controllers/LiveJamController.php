<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LiveJamController extends Controller
{
    private const STATES = ['playing_now', 'coming_up', 'pending', 'postponed', 'finished'];

    private const TRANSITION_SECONDS_PER_UNIQUE_USER = 45;

    /**
     * Cache TTL: 12 hours – enough to cover a full jam session evening.
     */
    private const CACHE_TTL_SECONDS = 43200;

    /**
     * Show the organiser management dashboard.
     */
    public function manage(Request $request, JamSession $jamSession): View
    {
        $this->authorize('update', $jamSession);

        $sets = $jamSession->sets()
            ->visibleTo($request->user())
            ->with(['owner', 'songs.slots.user'])
            ->get();

        $liveState = $this->getLiveState($jamSession->id);

        return view('sessions.live.manage', [
            'session' => $jamSession,
            'sets' => $sets,
            'liveState' => $liveState,
            'slotOptions' => Slot::options(),
        ]);
    }

    /**
     * Show the public live dashboard for participants.
     */
    public function dashboard(JamSession $jamSession): View
    {
        return view('sessions.live.dashboard', [
            'session' => $jamSession,
        ]);
    }

    /**
     * Return the current live state as JSON (used for polling from both dashboards).
     */
    public function data(Request $request, JamSession $jamSession): JsonResponse
    {
        $sets = $jamSession->sets()
            ->visibleTo($request->user())
            ->with(['owner', 'songs' => fn ($q) => $q->with(['slots.user'])])
            ->get();

        $liveState = $this->getLiveState($jamSession->id);
        $slotOptions = Slot::options();

        $setsData = $sets->map(function ($set) use ($liveState, $slotOptions): array {
            $stateEntry = collect($liveState['sets'] ?? [])->firstWhere('set_id', $set->id);
            $status = $stateEntry['status'] ?? 'pending';
            $order = $stateEntry['order'] ?? $set->position;

            $totalSlots = 0;
            $filledSlots = 0;
            $checkedInSlots = 0;
            $totalDurationSeconds = 0;
            $uniqueUsers = collect();

            foreach ($set->songs as $song) {
                if ($song->source !== null && $song->source !== '' && $song->duration !== null) {
                    $totalDurationSeconds += $song->duration;
                }

                foreach ($song->slots as $slot) {
                    $totalSlots++;

                    if ($slot->user_id !== null || $slot->manual_performer_name !== null) {
                        $filledSlots++;

                        if ($slot->user_id !== null) {
                            $uniqueUsers->push($slot->user_id);
                        }
                    }
                }
            }

            $uniqueUserCount = $uniqueUsers->unique()->count();

            if ($uniqueUserCount > 1) {
                $totalDurationSeconds += ($uniqueUserCount - 1) * self::TRANSITION_SECONDS_PER_UNIQUE_USER;
            }

            $health = $totalSlots > 0 ? round($filledSlots / $totalSlots * 100) : 0;

            return [
                'id' => $set->id,
                'name' => $set->name,
                'owner' => $set->owner?->name,
                'status' => $status,
                'order' => $order,
                'health' => $health,
                'total_slots' => $totalSlots,
                'filled_slots' => $filledSlots,
                'duration_seconds' => $totalDurationSeconds,
                'songs' => $set->songs->map(fn ($song) => [
                    'id' => $song->id,
                    'artist' => $song->artist,
                    'title' => $song->title,
                    'duration' => $song->duration,
                    'source' => $song->source,
                    'slots' => $song->slots->map(fn ($slot) => [
                        'id' => $slot->id,
                        'name' => $slotOptions[$slot->name] ?? $slot->name,
                        'user_name' => $slot->user?->name ?? $slot->manual_performer_name,
                        'filled' => $slot->user_id !== null || $slot->manual_performer_name !== null,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        });

        return response()->json([
            'sets' => $setsData->values()->all(),
            'updated_at' => $liveState['updated_at'] ?? null,
        ]);
    }

    /**
     * Save updated live state to the cache.
     *
     * @param  array{sets: array<int, array{set_id: int, status: string, order: int}>}  $validated
     */
    public function update(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('update', $jamSession);

        $validated = $request->validate([
            'sets' => ['required', 'array'],
            'sets.*.set_id' => ['required', 'integer', 'exists:sets,id'],
            'sets.*.status' => ['required', 'string', 'in:'.implode(',', self::STATES)],
            'sets.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $state = [
            'sets' => $validated['sets'],
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->cacheKey($jamSession->id), $state, self::CACHE_TTL_SECONDS);

        return response()->json(['message' => 'Live state updated.']);
    }

    /**
     * Clear the live state cache for a session.
     */
    public function clear(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('update', $jamSession);

        Cache::forget($this->cacheKey($jamSession->id));

        return response()->json(['message' => 'Live state cleared.']);
    }

    /**
     * Retrieve the current live state from cache, or return a default.
     *
     * @return array{sets: array<int, array{set_id: int, status: string, order: int}>, updated_at: string|null}
     */
    private function getLiveState(int $sessionId): array
    {
        return Cache::get($this->cacheKey($sessionId), ['sets' => [], 'updated_at' => null]);
    }

    private function cacheKey(int $sessionId): string
    {
        return 'live_jam_session:'.$sessionId;
    }
}
