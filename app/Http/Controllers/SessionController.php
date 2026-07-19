<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Session;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $sessions = Session::query()
            ->withCount(['sets' => fn ($query) => $query->visibleTo($user)])
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
        $this->authorize('create', Session::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        Session::create($validated);

        return to_route('sessions.index')->with('status', 'Jam session created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Session $session): View
    {
        $session->load([
            'sets' => fn ($query) => $query
                ->visibleTo(request()->user())
                ->with([
                    'owner',
                    'songs.slots.user',
                    'songs.slots.assignments.actor',
                    'songs.slots.assignments.target',
                ]),
        ]);

        return view('sessions.show', [
            'session' => $session,
            'slotOptions' => Slot::options(),
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Session $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $session->update($validated);

        return back()->with('status', 'Jam session updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Session $session): RedirectResponse
    {
        $this->authorize('delete', $session);

        $session->delete();

        return to_route('sessions.index')->with('status', 'Jam session deleted.');
    }
}
