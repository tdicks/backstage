<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamStandardSong;
use App\Models\JamStandardSongRequest;
use App\Models\JamStandardUserSlot;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JamStandardController extends Controller
{
    private const CATALOG_SONGS_PER_PAGE = 15;

    public function index(Request $request): View|JsonResponse
    {
        $selectedPerformerIds = collect($request->input('user_ids', []))
            ->filter(fn ($userId) => filter_var($userId, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();
        $currentUserId = (int) $request->user()->id;
        $selectedPerformers = User::query()
            ->whereIn('id', $selectedPerformerIds)
            ->where(fn ($query) => $query
                ->where('hide_from_directory', false)
                ->orWhere('id', $currentUserId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $catalogSongs = JamStandardSong::query()
            ->active()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $query->where(function ($songQuery) use ($request): void {
                    $songQuery->where('artist', 'like', '%'.$request->string('q')->trim().'%')
                        ->orWhere('title', 'like', '%'.$request->string('q')->trim().'%');
                });
            })
            ->when($selectedPerformers->isNotEmpty(), function ($query) use ($selectedPerformers): void {
                foreach ($selectedPerformers->modelKeys() as $performerId) {
                    $query->whereHas('userSlots', fn ($slotQuery) => $slotQuery->where('user_id', $performerId));
                }
            })
            ->orderBy('artist')
            ->orderBy('title')
            ->with(['slots', 'userSlots' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->paginate(self::CATALOG_SONGS_PER_PAGE)
            ->withQueryString();
        $searchedUserSlots = $selectedPerformers->isEmpty()
            ? collect()
            : JamStandardUserSlot::query()
                ->whereIn('user_id', $selectedPerformers->modelKeys())
                ->get()
                ->groupBy('jam_standard_song_id')
                ->map(fn ($slots) => $slots->groupBy('slot_name')
                    ->map(fn ($slotUsers) => $slotUsers->pluck('user_id')->unique()->values()->all())
                    ->all());

        $recentCapabilityCounts = JamStandardUserSlot::recentCapabilityCountsForSongs(
            $catalogSongs->getCollection()->pluck('id'),
        );

        $catalogSongs->getCollection()->each(function (JamStandardSong $song) use ($recentCapabilityCounts): void {
            $song->setAttribute('recent_capability_counts', $recentCapabilityCounts[$song->id] ?? []);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'songs' => $catalogSongs->getCollection()->map(fn (JamStandardSong $song) => [
                    'id' => $song->id,
                    'artist' => $song->artist,
                    'title' => $song->title,
                    'notes' => $song->notes,
                    'duration' => $song->duration,
                    'source' => $song->source,
                    'band_template_id' => $song->band_template_id,
                    'slots' => $song->slots->map(fn ($slot) => [
                        'name' => $slot->name,
                        'recent_capability_count' => max(0, (int) ($song->recent_capability_counts[$slot->name] ?? 0)),
                    ])->values(),
                    'user_slot_names' => $song->userSlots->pluck('slot_name')->values(),
                    'performer_slots' => $searchedUserSlots[$song->id] ?? [],
                ])->values(),
                'performers' => $selectedPerformers->values(),
                'pagination' => [
                    'current_page' => $catalogSongs->currentPage(),
                    'last_page' => $catalogSongs->lastPage(),
                    'total' => $catalogSongs->total(),
                    'from' => $catalogSongs->firstItem(),
                    'to' => $catalogSongs->lastItem(),
                ],
            ]);
        }

        return view('jam-standards.catalog', [
            'catalogSongs' => $catalogSongs,
            'sessions' => JamSession::query()
                ->visibleTo($request->user())
                ->where('is_archived', false)
                ->orderByDesc('date')
                ->get(['id', 'name', 'date', 'is_closed']),
            'slotOptions' => Slot::options(),
            'slotConflicts' => collect(SlotType::query()
                ->with('conflicts:key')
                ->where('active', true)
                ->get(['id', 'key'])
                ->reduce(function (array $conflicts, SlotType $slotType): array {
                    $conflicts[$slotType->key] ??= [];

                    foreach ($slotType->conflicts->pluck('key') as $conflictingKey) {
                        $conflicts[$slotType->key][] = $conflictingKey;
                        $conflicts[$conflictingKey][] = $slotType->key;
                    }

                    return $conflicts;
                }, []))
                ->map(fn (array $conflictingKeys) => collect($conflictingKeys)->unique()->values()->all())
                ->all(),
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'users' => User::query()
                ->where(fn ($query) => $query
                    ->where('hide_from_directory', false)
                    ->orWhere('id', $currentUserId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'pendingRequests' => JamStandardSongRequest::query()
                ->where('status', JamStandardSongRequest::STATUS_PENDING)
                ->with(['requester', 'bandTemplate'])
                ->latest()
                ->get(),
            'selectedPerformers' => $selectedPerformers,
            'searchedUserSlots' => $searchedUserSlots,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1', 'required_with:source'],
            'source' => ['nullable', 'string', 'in:deezer', 'required_with:duration'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id', 'required_without:slot_names'],
            'slot_names' => ['nullable', 'array', 'min:1', 'required_without:band_template_id'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $nearMatches = JamStandardSong::nearMatchesFor($validated['artist'], $validated['title']);

        $catalogSong = DB::transaction(function () use ($validated, $request): JamStandardSong {
            $catalogSong = JamStandardSong::query()->create([
                'artist' => $validated['artist'],
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'source' => $validated['source'] ?? null,
                'band_template_id' => $validated['band_template_id'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->createSlots($catalogSong, $validated['band_template_id'] ?? null, $validated['slot_names'] ?? []);

            return $catalogSong;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'song' => $catalogSong->load('slots'),
                'near_matches' => $nearMatches->map(fn (JamStandardSong $song) => $song->only(['artist', 'title']))->all(),
            ], 201);
        }

        $redirect = to_route('jam-standards.index')->with('status', 'Catalog song added.');

        if ($nearMatches->isNotEmpty()) {
            return $redirect
                ->with('warning', 'Possible duplicates found in the catalog.')
                ->with('duplicateSuggestions', $nearMatches->map(fn (JamStandardSong $song) => [
                    'artist' => $song->artist,
                    'title' => $song->title,
                ])->all());
        }

        return $redirect;
    }

    public function update(Request $request, JamStandardSong $jamStandardSong): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1', 'required_with:source'],
            'source' => ['nullable', 'string', 'in:deezer', 'required_with:duration'],
            'band_template_id' => ['nullable', 'integer', 'exists:band_templates,id', 'required_without:slot_names'],
            'slot_names' => ['nullable', 'array', 'min:1', 'required_without:band_template_id'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        DB::transaction(function () use ($jamStandardSong, $validated): void {
            $jamStandardSong->update([
                'artist' => $validated['artist'],
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'source' => $validated['source'] ?? null,
                'band_template_id' => $validated['band_template_id'] ?? null,
            ]);

            $jamStandardSong->slots()->delete();
            $this->createSlots($jamStandardSong, $validated['band_template_id'] ?? null, $validated['slot_names'] ?? []);
            $jamStandardSong->userSlots()
                ->whereNotIn('slot_name', $jamStandardSong->slots()->pluck('name'))
                ->delete();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'song' => $jamStandardSong->refresh()->load('slots'),
            ]);
        }

        return to_route('jam-standards.index')->with('status', 'Catalog song updated.');
    }

    public function destroy(Request $request, JamStandardSong $jamStandardSong): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $catalogSongId = $jamStandardSong->id;
        $jamStandardSong->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted_id' => $catalogSongId]);
        }

        return to_route('jam-standards.index')->with('status', 'Catalog song deleted.');
    }

    public function coverage(Request $request, JamStandardSong $jamStandardSong): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $coverage = JamStandardUserSlot::query()
            ->where('jam_standard_song_id', $jamStandardSong->id)
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($userSlots) => [
                'id' => $userSlots->first()->user_id,
                'name' => $userSlots->first()->user->name,
                'slot_names' => $userSlots->pluck('slot_name')->values(),
            ])
            ->sortBy('name')
            ->values();

        return response()->json([
            'song' => $jamStandardSong->only(['id', 'artist', 'title']),
            'coverage' => $coverage,
        ]);
    }

    /** @param list<string> $slotNames */
    private function createSlots(JamStandardSong $catalogSong, ?int $templateId, array $slotNames): void
    {
        $templateSlots = $templateId === null
            ? collect()
            : BandTemplate::query()->findOrFail($templateId)->slots->pluck('name');

        collect($slotNames)
            ->merge($templateSlots)
            ->map(fn ($slotName) => (string) $slotName)
            ->filter(fn (string $slotName) => in_array($slotName, Slot::keys(), true))
            ->unique()
            ->values()
            ->each(fn (string $slotName, int $position) => $catalogSong->slots()->create([
                'name' => $slotName,
                'position' => $position + 1,
            ]));
    }
}
