<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JamSessionController extends Controller
{
    private const LOADING_ONE_LINERS = [
        'Inserting the Spinal Tap video cassette...',
        'Dusting off the Marshall stack...',
        'Feeding the bass players...',
        'Changing some guitar strings...',
        "Confiscating the singer's beer...",
        'Tuning the triangle for optimal resonance...',
        'Setting the fog machine to maximum drama...',
        'Arguing about whether this riff is in drop D...',
        'Waking the drummer from a 17-minute solo dream...',
        'Polishing the leather jackets for stage readiness...',
        'Searching eBay for Jackson Soloists...',
        "Trotting down the Witch's Brew for a pint...",
        "Searching for Status Quo's fourth chord...",
        'Yeah but these go to eleven...',
    ];

    public function index(): View
    {
        $sessions = JamSession::query()
            ->visibleTo(request()->user())
            ->where('is_archived', false)
            ->withCount(['sets' => fn ($query) => $query->visibleTo(request()->user())])
            ->latest('date')
            ->get();

        $hasArchivedJamSessions = JamSession::query()
            ->visibleTo(request()->user())
            ->where('is_archived', true)
            ->exists();

        return view('sessions.index', [
            'sessions' => $sessions,
            'isArchiveView' => false,
            'hasArchivedJamSessions' => $hasArchivedJamSessions,
            'pageTitle' => 'Jam Sessions',
        ]);
    }

    public function archive(): View
    {
        $sessions = JamSession::query()
            ->visibleTo(request()->user())
            ->where('is_archived', true)
            ->withCount(['sets' => fn ($query) => $query->visibleTo(request()->user())])
            ->latest('date')
            ->get();

        return view('sessions.index', [
            'sessions' => $sessions,
            'isArchiveView' => true,
            'pageTitle' => 'Session Archive',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', JamSession::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'is_closed' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'allow_checkins' => ['nullable', 'boolean'],
        ]);

        $isClosed = (bool) ($validated['is_closed'] ?? false);
        $allowCheckins = $isClosed
            ? false
            : (bool) ($validated['allow_checkins'] ?? true);

        JamSession::create([
            ...$validated,
            'is_closed' => $isClosed,
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'is_archived' => (bool) ($validated['is_archived'] ?? false),
            'allow_checkins' => $allowCheckins,
        ]);

        return to_route('sessions.index')->with('status', 'Jam session created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JamSession $jamSession): View
    {
        $this->authorize('view', $jamSession);

        $jamSession->loadCount(['sets' => fn ($query) => $query->visibleTo(request()->user())]);

        return view('sessions.show', [
            'session' => $jamSession,
            'loadingOneLiner' => self::LOADING_ONE_LINERS[array_rand(self::LOADING_ONE_LINERS)],
        ]);
    }

    public function sets(Request $request, JamSession $jamSession): View
    {
        $this->authorize('view', $jamSession);

        $jamSession->load([
            'sets' => function ($query) use ($request): void {
                $query->visibleTo($request->user())
                    ->with([
                        'session',
                        'owner',
                        'songRequests.requester',
                        'songRequests.responder',
                        'songRequests.bandTemplate',
                        'songs.slots.user',
                        'songs.slots.assignments.actor',
                        'songs.slots.assignments.target',
                    ]);
            },
        ]);

        return view('sessions.partials.set-cards', $this->sessionSetsViewData($jamSession));
    }

    public function activity(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('view', $jamSession);

        $validated = $request->validate([
            'song_ids' => ['array'],
            'song_ids.*' => ['integer'],
        ]);

        $songIds = collect($validated['song_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $viewData = $this->slotRowViewData();

        $songs = $songIds->isEmpty()
            ? collect()
            : Song::query()
                ->whereIn('id', $songIds)
                ->whereHas('set', fn ($query) => $query->where('jam_session_id', $jamSession->id)->visibleTo($request->user()))
                ->with([
                    'set.session',
                    'set.owner',
                    'slots.user',
                    'slots.assignments.actor',
                    'slots.assignments.target',
                ])
                ->orderBy('position')
                ->orderBy('id')
                ->get();

        return response()->json([
            'approval_count' => MySetsController::pendingApprovalCount($request->user()),
            'songs' => $songs->mapWithKeys(fn (Song $song) => [
                $song->id => [
                    'slots_html' => view('components.sessions.song-slots', [
                        'song' => $song,
                        'set' => $song->set,
                        'users' => $viewData['users'],
                        'slotOptions' => $viewData['slotOptions'],
                        'isSetOwner' => $song->set->owner_id === $request->user()->id,
                        'canManageSet' => $request->user()->is_admin || $song->set->owner_id === $request->user()->id || $song->set->isCollaborator($request->user()),
                    ])->render(),
                ],
            ]),
        ]);
    }

    private function sessionSetsViewData(JamSession $jamSession): array
    {
        return [
            'session' => $jamSession,
            'sessions' => JamSession::query()
                ->visibleTo(request()->user())
                ->orderByDesc('date')
                ->get(['id', 'name', 'date']),
            'slotOptions' => Slot::options(),
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }

    private function slotRowViewData(): array
    {
        return [
            'slotOptions' => Slot::options(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JamSession $jamSession): RedirectResponse
    {
        $this->authorize('update', $jamSession);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'is_closed' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'allow_checkins' => ['nullable', 'boolean'],
        ]);

        $isClosed = (bool) ($validated['is_closed'] ?? $jamSession->is_closed);
        $allowCheckins = $isClosed
            ? false
            : (bool) ($validated['allow_checkins'] ?? $jamSession->allow_checkins);

        $wasAllowingCheckins = $jamSession->allow_checkins;

        $jamSession->update([
            ...$validated,
            'is_closed' => $isClosed,
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'is_archived' => (bool) ($validated['is_archived'] ?? $jamSession->is_archived),
            'allow_checkins' => $allowCheckins,
        ]);

        if ($wasAllowingCheckins && ! $jamSession->allow_checkins) {
            $jamSession->signIns()->delete();
        }

        return back()->with('status', 'Jam session updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JamSession $jamSession): RedirectResponse
    {
        $this->authorize('delete', $jamSession);

        $jamSession->delete();

        return to_route('sessions.index')->with('status', 'Jam session deleted.');
    }
}
