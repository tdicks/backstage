<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SlotTypeConflictController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.slot-conflicts.index', [
            'slotTypes' => SlotType::query()
                ->with('conflicts')
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, SlotType $slotType): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'conflict_id' => [
                'required',
                'integer',
                Rule::exists('slot_types', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('id', '<>', $slotType->id)),
            ],
            'enabled' => ['required', 'boolean'],
        ]);

        $conflictingSlotTypeId = (int) $validated['conflict_id'];
        $enabled = (bool) $validated['enabled'];

        DB::transaction(function () use ($slotType, $conflictingSlotTypeId, $enabled): void {
            foreach ([[$slotType->id, $conflictingSlotTypeId], [$conflictingSlotTypeId, $slotType->id]] as [$sourceId, $targetId]) {
                if ($enabled) {
                    DB::table('slot_type_conflicts')->updateOrInsert(
                        [
                            'slot_type_id' => $sourceId,
                            'conflicting_slot_type_id' => $targetId,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    continue;
                }

                DB::table('slot_type_conflicts')
                    ->where('slot_type_id', $sourceId)
                    ->where('conflicting_slot_type_id', $targetId)
                    ->delete();
            }
        });

        return response()->json([
            'message' => $slotType->name.' conflict updated.',
            'slot_type_id' => $slotType->id,
            'conflicting_slot_type_id' => $conflictingSlotTypeId,
            'enabled' => $enabled,
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
