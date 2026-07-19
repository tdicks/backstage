<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BandTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BandTemplate::class);

        return view('band-templates.index', [
            'templates' => BandTemplate::query()->with('slots')->orderBy('name')->get(),
            'slotOptions' => Slot::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BandTemplate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slot_names' => ['required', 'array', 'min:1'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $template = BandTemplate::create(['name' => $validated['name']]);

        foreach (array_unique($validated['slot_names']) as $slotName) {
            $template->slots()->create(['name' => $slotName]);
        }

        return back()->with('status', 'Band template created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BandTemplate $bandTemplate): RedirectResponse
    {
        $this->authorize('update', $bandTemplate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slot_names' => ['required', 'array', 'min:1'],
            'slot_names.*' => ['string', 'in:'.implode(',', Slot::keys())],
        ]);

        $bandTemplate->update(['name' => $validated['name']]);

        $bandTemplate->slots()->delete();

        foreach (array_unique($validated['slot_names']) as $slotName) {
            $bandTemplate->slots()->create(['name' => $slotName]);
        }

        return back()->with('status', 'Band template updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BandTemplate $bandTemplate): RedirectResponse
    {
        $this->authorize('delete', $bandTemplate);

        $bandTemplate->delete();

        return back()->with('status', 'Band template deleted.');
    }
}
