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

        $jamSession->sets()->create([
            ...$validated,
            'owner_id' => $request->user()->id,
            'performed' => false,
        ]);

        return back()->with('status', 'Set created.');
    }

    public function update(Request $request, Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'performed' => ['nullable', 'boolean'],
        ]);

        $set->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'performed' => (bool) ($validated['performed'] ?? false),
        ]);

        return back()->with('status', 'Set updated.');
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
