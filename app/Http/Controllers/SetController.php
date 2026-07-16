<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Set;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetController extends Controller
{
    public function store(Request $request, JamSession $jamSession): RedirectResponse
    {
        $this->authorize('create', Set::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $nextPosition = ((int) $jamSession->sets()->max('position')) + 1;

        $jamSession->sets()->create([
            ...$validated,
            'owner_id' => $request->user()->id,
            'position' => $nextPosition,
            'performed' => false,
        ]);

        return back()->with('status', 'Set created.');
    }

    public function update(Request $request, Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        $isAdmin = $request->user()->is_admin;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'performed' => ['nullable', 'boolean'],
            'jam_session_id' => ['nullable', 'integer', 'exists:jam_sessions,id'],
        ];

        if ($isAdmin) {
            $rules['owner_id'] = ['required', 'integer', 'exists:users,id'];
        }

        $validated = $request->validate($rules);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? $set->position,
            'performed' => (bool) ($validated['performed'] ?? false),
            'jam_session_id' => $validated['jam_session_id'] ?? $set->jam_session_id,
        ];

        if ($isAdmin) {
            $updateData['owner_id'] = $validated['owner_id'];
        }

        $set->update($updateData);

        return back()->with('status', 'Set updated.');
    }

    public function closeSignups(Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        if (! $set->signups_open) {
            return back()->with('status', 'Sign ups are already closed.');
        }

        $set->update([
            'signups_open' => false,
        ]);

        return back()->with('status', 'Sign ups closed.');
    }

    public function openSignups(Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        if ($set->signups_open) {
            return back()->with('status', 'Sign ups are already open.');
        }

        $set->update([
            'signups_open' => true,
        ]);

        return back()->with('status', 'Sign ups re-opened.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Set $set): RedirectResponse
    {
        $this->authorize('delete', $set);

        $set->delete();

        return back()->with('status', 'Set removed.');
    }
}
