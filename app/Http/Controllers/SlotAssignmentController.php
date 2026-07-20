<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Models\SlotAssignment;
use App\Services\SlotCompatibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'status' => SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
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
        if (! in_array($slotAssignment->status, [SlotAssignment::STATUS_AWAITING_TARGET_CONSENT, SlotAssignment::STATUS_PENDING], true)) {
            return back()->with('status', 'This assignment has already been processed.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        $user = $request->user();
        $slotAssignment->load('slot.song.set');

        if ($slotAssignment->status === SlotAssignment::STATUS_AWAITING_TARGET_CONSENT) {
            if ($slotAssignment->type !== SlotAssignment::TYPE_PROPOSAL) {
                abort(403);
            }

            $canConsent = $user->is_admin
                || $slotAssignment->target_user_id === $user->id
                || ($slotAssignment->actor_user_id === $user->id && $validated['status'] === SlotAssignment::STATUS_REJECTED);

            if (! $canConsent) {
                abort(403);
            }

            $ownerRecommended = $slotAssignment->actor_user_id === $slotAssignment->slot->song->set->owner_id;
            $targetAccepted = $validated['status'] === SlotAssignment::STATUS_ACCEPTED;

            DB::transaction(function () use ($slotAssignment, $targetAccepted, $ownerRecommended): void {
                $slotAssignment->update([
                    'status' => $targetAccepted
                        ? ($ownerRecommended ? SlotAssignment::STATUS_ACCEPTED : SlotAssignment::STATUS_PENDING)
                        : SlotAssignment::STATUS_REJECTED,
                    'responded_at' => now(),
                ]);

                if ($targetAccepted && $ownerRecommended) {
                    $this->assignSlotAndReleaseConflicts($slotAssignment);
                }
            });

            if ($request->expectsJson()) {
                $slotAssignment->slot->load('user');

                return response()->json([
                    'message' => match (true) {
                        $targetAccepted && $ownerRecommended => 'Recommendation accepted and slot assigned.',
                        $targetAccepted => 'Recommendation sent to set owner.',
                        default => 'Recommendation response recorded.',
                    },
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

            return back()->with('status', match (true) {
                $targetAccepted && $ownerRecommended => 'Recommendation accepted and slot assigned.',
                $targetAccepted => 'Recommendation sent to set owner.',
                default => 'Recommendation response recorded.',
            });
        }

        $canRespond = $user->is_admin;

        if ($slotAssignment->type === SlotAssignment::TYPE_REQUEST) {
            $canRespond = $canRespond
                || $slotAssignment->slot->song->set->owner_id === $user->id
                || $slotAssignment->slot->song->set->isCollaborator($user)
                || $slotAssignment->actor_user_id === $user->id;
        }

        if ($slotAssignment->type === SlotAssignment::TYPE_PROPOSAL) {
            $canRespond = $canRespond
                || $slotAssignment->slot->song->set->owner_id === $user->id
                || $slotAssignment->slot->song->set->isCollaborator($user)
                || ($slotAssignment->target_user_id === $user->id && $validated['status'] === SlotAssignment::STATUS_REJECTED);
        }

        if (! $canRespond) {
            abort(403);
        }

        DB::transaction(function () use ($slotAssignment, $validated): void {
            $slotAssignment->update([
                'status' => $validated['status'],
                'responded_at' => now(),
            ]);

            if ($validated['status'] === SlotAssignment::STATUS_ACCEPTED) {
                $this->assignSlotAndReleaseConflicts($slotAssignment);
            }
        });

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

    private function assignSlotAndReleaseConflicts(SlotAssignment $slotAssignment): void
    {
        $slotAssignment->loadMissing('slot.song');

        $conflictingSlot = SlotCompatibility::conflictingSlotForSlot($slotAssignment->target_user_id, $slotAssignment->slot);

        if ($conflictingSlot) {
            $conflictingSlot->update([
                'user_id' => null,
                'manual_performer_name' => null,
            ]);
        }

        $slotAssignment->slot->update([
            'user_id' => $slotAssignment->target_user_id,
            'manual_performer_name' => null,
        ]);

        $this->rejectSupersededSelfRequests($slotAssignment);
    }

    private function rejectSupersededSelfRequests(SlotAssignment $slotAssignment): void
    {
        SlotAssignment::query()
            ->whereKeyNot($slotAssignment->id)
            ->where('actor_user_id', $slotAssignment->target_user_id)
            ->where('target_user_id', $slotAssignment->target_user_id)
            ->where('type', SlotAssignment::TYPE_REQUEST)
            ->where('status', SlotAssignment::STATUS_PENDING)
            ->whereHas('slot', fn ($query) => $query->where('song_id', $slotAssignment->slot->song_id))
            ->update([
                'status' => SlotAssignment::STATUS_REJECTED,
                'responded_at' => now(),
            ]);
    }
}
