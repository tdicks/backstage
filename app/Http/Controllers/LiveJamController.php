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
     * Redirect a short live code URL to the full live dashboard.
     */
    public function shortDashboard(string $liveCode)
    {
        $jamSession = JamSession::query()
            ->where('live_code', $liveCode)
            ->firstOrFail();

        return redirect()->route('sessions.live.dashboard', $jamSession);
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

        // Load check-in status for this jam session
        $checkedInUserIds = $jamSession->signIns()
            ->whereNotNull('signed_in_at')
            ->pluck('user_id')
            ->unique();

        $setsData = $sets->map(function ($set) use ($liveState, $slotOptions, $checkedInUserIds): array {
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
                'feature_set' => $set->feature_set,
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
                        'checked_in' => $slot->user_id !== null && $checkedInUserIds->contains($slot->user_id),
                    ])->values()->all(),
                ])->values()->all(),
            ];
        })->values()->all();

        // Add live sets from cache
        $liveSets = collect($liveState['sets'] ?? [])
            ->filter(fn ($s) => is_string($s['set_id']) && str_starts_with($s['set_id'], 'live_'))
            ->map(function ($s) {
                $liveData = $s['liveSetData'] ?? [];

                return [
                    'id' => $s['set_id'],
                    'name' => $liveData['name'] ?? 'Unnamed Set',
                    'owner' => $liveData['owner'] ?? null,
                    'feature_set' => (bool) ($liveData['feature_set'] ?? false),
                    'status' => $s['status'] ?? 'pending',
                    'order' => $s['order'] ?? 0,
                    'health' => 0,
                    'total_slots' => 0,
                    'filled_slots' => 0,
                    'duration_seconds' => 0,
                    'songs' => [],
                    'isLiveSet' => true,
                    'participants' => $liveData['participants'] ?? null,
                    'details' => $liveData['details'] ?? null,
                    'liveSetData' => $liveData,
                ];
            })
            ->values()
            ->all();

        // Merge database sets with live sets
        $allSets = array_merge($setsData, $liveSets);

        // Only apply status/order from cache if it exists
        // If there's no cache, all sets default to 'pending' status with order 0
        $hasCache = ! empty($liveState['sets']);

        if (! $hasCache) {
            // First load: assign default pending status with incrementing orders
            foreach ($allSets as $idx => $set) {
                if ($set['status'] === 'pending') {
                    $allSets[$idx]['order'] = $idx;
                }
            }
        }

        return response()->json([
            'sets' => $allSets,
            'updated_at' => $liveState['updated_at'] ?? null,
        ]);
    }

    /**
     * Save updated live state to the cache.
     */
    public function update(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('update', $jamSession);

        $validated = $request->validate([
            'sets' => ['required', 'array'],
            'sets.*.set_id' => ['required'],  // Can be int (database) or string (live set)
            'sets.*.status' => ['required', 'string', 'in:'.implode(',', self::STATES)],
            'sets.*.order' => ['required', 'integer', 'min:-1'],
            'sets.*.isLiveSet' => ['nullable', 'boolean'],
            'sets.*.liveSetData' => ['nullable', 'array'],
            'sets.*.liveSetData.name' => ['nullable', 'string'],
            'sets.*.liveSetData.owner' => ['nullable', 'string'],
            'sets.*.liveSetData.participants' => ['nullable', 'string'],
            'sets.*.liveSetData.details' => ['nullable', 'string'],
        ]);

        // Validate that database set_ids exist
        $databaseSetIds = collect($validated['sets'])
            ->filter(fn ($s) => ! is_string($s['set_id']) || ! str_starts_with((string) $s['set_id'], 'live_'))
            ->pluck('set_id')
            ->unique()
            ->values();

        if ($databaseSetIds->count() > 0) {
            $existingIds = $jamSession->sets()->whereIn('id', $databaseSetIds)->pluck('id');
            $missingIds = $databaseSetIds->diff($existingIds);

            if ($missingIds->count() > 0) {
                return response()->json([
                    'message' => 'One or more sets do not exist.',
                    'errors' => ['sets' => ['One or more set IDs are invalid.']],
                ], 422);
            }
        }

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
