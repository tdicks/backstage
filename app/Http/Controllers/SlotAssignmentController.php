<?php

namespace App\Http\Controllers;

use App\Models\SlotAssignment;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SlotAssignmentController extends Controller
{
    public function request(Request $request, Slot $slot): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Request submitted to set owner.',
            ], 201);
        }

        return back()->with('status', 'Request submitted to set owner.');
    }

    public function propose(Request $request, Slot $slot): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        $slot->load('song.set');

        if (! $slot->song->set->signups_open) {
            return back()->with('status', 'Sign ups are closed for this set.');
        }

        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('hide_from_slot_proposals', false)),
            ],
            'message' => ['nullable', 'string'],
        ]);

        $slot->assignments()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $validated['target_user_id'],
            'type' => SlotAssignment::TYPE_PROPOSAL,
            'status' => SlotAssignment::STATUS_PENDING,
            'message' => $validated['message'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Proposal sent.',
            ], 201);
        }

        return back()->with('status', 'Proposal sent.');
    }

    public function respond(Request $request, SlotAssignment $slotAssignment): JsonResponse|RedirectResponse
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
                'manual_performer_name' => null,
            ]);
        }

        if ($request->expectsJson()) {
            $slotAssignment->slot->load('user');

            return response()->json([
                'message' => 'Assignment response recorded.',
                'slot' => [
                    'id' => $slotAssignment->slot->id,
                    'name' => $slotAssignment->slot->name,
                    'label' => Slot::options()[$slotAssignment->slot->name] ?? $slotAssignment->slot->name,
                    'user_id' => $slotAssignment->slot->user_id,
                    'user_name' => $slotAssignment->slot->assignedPerformerName(),
                    'is_open' => $slotAssignment->slot->isOpen(),
                ],
            ]);
        }

        return back()->with('status', 'Assignment response recorded.');
    }
}
