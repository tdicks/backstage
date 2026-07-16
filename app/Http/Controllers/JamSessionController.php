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
    public function index(): View
    {
        $sessions = JamSession::query()
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
        ]);

        JamSession::create($validated);

        return to_route('sessions.index')->with('status', 'Jam session created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JamSession $jamSession): View
    {
        $jamSession->load([
            'sets.owner',
            'sets.songRequests.requester',
            'sets.songRequests.responder',
            'sets.songRequests.bandTemplate',
            'sets.songs.slots.user',
            'sets.songs.slots.assignments.actor',
            'sets.songs.slots.assignments.target',
        ]);

        return view('sessions.show', [
            'session' => $jamSession,
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
        ]);

        $jamSession->update($validated);

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
