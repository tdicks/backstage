<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JamSessionController extends Controller
{
    private const LOADING_ONE_LINERS = [
        'Inserting the Spinal Tap video cassette...',
        'Dusting off the Marshall stack...',
        'Feeding the bass players...',
        "Changing some guitar strings...",
        "Confiscating the singer's beer...",
        'Tuning the triangle for optimal resonance...',
        'Setting the fog machine to maximum drama...',
        'Arguing about whether this riff is in drop D...',
        'Waking the drummer from a 17-minute solo dream...',
        'Polishing the leather jackets for stage readiness...',
        'Searching eBay for Jackson Soloists...',
        "Trotting down the Witch's Brew for a pint...",
        "Searching for Status Quo's fourth chord...",
        "Yeah but these go to eleven..."
    ];

    public function index(): View
    {
        $sessions = JamSession::query()
            ->visibleTo(request()->user())
            ->withCount('sets')
            ->latest('date')
            ->get();

        return view('sessions.index', [
            'sessions' => $sessions,
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
            'is_hidden' => ['nullable', 'boolean'],
        ]);

        JamSession::create([
            ...$validated,
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
        ]);

        return to_route('sessions.index')->with('status', 'Jam session created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JamSession $jamSession): View
    {
        $this->authorize('view', $jamSession);

        $jamSession->loadCount('sets');

        return view('sessions.show', [
            'session' => $jamSession,
            'loadingOneLiner' => self::LOADING_ONE_LINERS[array_rand(self::LOADING_ONE_LINERS)],
        ]);
    }

    public function sets(JamSession $jamSession): View
    {
        $this->authorize('view', $jamSession);

        $jamSession->load([
            'sets.session',
            'sets.owner',
            'sets.songRequests.requester',
            'sets.songRequests.responder',
            'sets.songRequests.bandTemplate',
            'sets.songs.slots.user',
            'sets.songs.slots.assignments.actor',
            'sets.songs.slots.assignments.target',
        ]);

        return view('sessions.partials.set-cards', [
            'session' => $jamSession,
            'sessions' => JamSession::query()
                ->visibleTo(request()->user())
                ->orderByDesc('date')
                ->get(['id', 'name', 'date']),
            'slotOptions' => Slot::options(),
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
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
        ]);

        $jamSession->update([
            ...$validated,
            'is_closed' => (bool) ($validated['is_closed'] ?? false),
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
        ]);

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
