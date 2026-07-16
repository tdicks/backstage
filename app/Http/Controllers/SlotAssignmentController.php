<?php

namespace App\Http\Controllers;

use App\Models\SlotAssignment;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlotAssignmentController extends Controller
{
    public function request(Request $request, Slot $slot): RedirectResponse
    {
        $user = $request->user();
        $slot->load('song.set');

        if (! $slot->song->set->signups_open) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        if ($slot->user_id === $user->id) {
            return back()->with('status', 'You already have this slot.');
        }

        $slot->assignments()->create([
            'actor_user_id' => $user->id,
            'target_user_id' => $user->id,
            'type' => SlotAssignment::TYPE_REQUEST,
            'status' => SlotAssignment::STATUS_PENDING,
            'message' => $request->string('message')->toString() ?: null,
        ]);

        return back()->with('status', 'Request submitted to set owner.');
    }

    public function propose(Request $request, Slot $slot): RedirectResponse
    {
        $actor = $request->user();
        $slot->load('song.set');

        if (! $slot->song->set->signups_open) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        $validated = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string'],
        ]);

        $slot->assignments()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $validated['target_user_id'],
            'type' => SlotAssignment::TYPE_PROPOSAL,
            'status' => SlotAssignment::STATUS_PENDING,
            'message' => $validated['message'] ?? null,
        ]);

        return back()->with('status', 'Proposal sent.');
    }

    public function respond(Request $request, SlotAssignment $slotAssignment): RedirectResponse
    {
        if ($slotAssignment->status !== SlotAssignment::STATUS_PENDING) {
            return back()->with('status', 'This assignment has already been processed.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        $user = $request->user();
        $slotAssignment->load('slot.song.set');

        $canRespond = $user->is_admin;

        if ($slotAssignment->type === SlotAssignment::TYPE_REQUEST) {
            $canRespond = $canRespond || $slotAssignment->slot->song->set->owner_id === $user->id || $slotAssignment->actor_user_id === $user->id;
        }

        if ($slotAssignment->type === SlotAssignment::TYPE_PROPOSAL) {
            $canRespond = $canRespond
                || $slotAssignment->target_user_id === $user->id
                || $slotAssignment->actor_user_id === $user->id
                || $slotAssignment->slot->song->set->owner_id === $user->id;
        }

        if (! $canRespond) {
            abort(403);
        }

        $slotAssignment->update([
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);

        if ($validated['status'] === SlotAssignment::STATUS_ACCEPTED) {
            $slotAssignment->slot->update([
                'user_id' => $slotAssignment->target_user_id,
            ]);
        }

        return back()->with('status', 'Assignment response recorded.');
    }
}
