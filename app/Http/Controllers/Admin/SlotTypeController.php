<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SlotTypeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $key = Str::slug($validated['name'], '_');

        abort_if($key === '', 422, 'Enter a slot type name containing letters or numbers.');

        $request->validate([
            'name' => [Rule::unique('slot_types', 'name')],
        ]);

        $slotType = SlotType::query()->create([
            'key' => $key,
            'name' => $validated['name'],
            'sort_order' => ((int) SlotType::query()->max('sort_order')) + 10,
            'active' => true,
        ]);

        return response()->json([
            'message' => $slotType->name.' added.',
            'slot_type' => $this->slotTypeData($slotType),
        ], 201);
    }

    public function update(Request $request, SlotType $slotType): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('slot_types', 'name')->ignore($slotType)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:99999'],
            'active' => ['required', 'boolean'],
        ]);

        $slotType->update($validated);

        return response()->json([
            'message' => $slotType->name.' updated.',
            'slot_type' => $this->slotTypeData($slotType),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    /**
     * @return array{id: int, key: string, name: string, sort_order: int, active: bool}
     */
    private function slotTypeData(SlotType $slotType): array
    {
        return [
            'id' => $slotType->id,
            'key' => $slotType->key,
            'name' => $slotType->name,
            'sort_order' => $slotType->sort_order,
            'active' => $slotType->active,
        ];
    }
}
