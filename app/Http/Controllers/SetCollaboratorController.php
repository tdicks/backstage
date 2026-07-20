<?php

namespace App\Http\Controllers;

use App\Models\Set;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetCollaboratorController extends Controller
{
    /**
     * Returns users matching the search query for the collaborator picker,
     * excluding the set owner.
     */
    public function users(Request $request, Set $set): JsonResponse
    {
        $this->authorize('update', $set);

        $query = trim((string) $request->string('q'));

        $users = User::query()
            ->where('id', '!=', $set->owner_id)
            ->when(
                $query !== '',
                fn ($q) => $q->where('name', 'like', '%'.$query.'%')
            )
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json([
            'users' => $users->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
        ]);
    }

    /**
     * Updates the collaborator list for the given set.
     */
    public function update(Request $request, Set $set): JsonResponse
    {
        $this->authorize('update', $set);

        $validated = $request->validate([
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $rawIds = array_values(array_map('intval', $validated['collaborator_ids'] ?? []));

        // Remove the set owner from collaborators if mistakenly included.
        $collaboratorIds = array_values(array_filter($rawIds, fn (int $id) => $id !== $set->owner_id));

        $set->update(['collaborator_ids' => $collaboratorIds ?: null]);

        $collaborators = User::query()
            ->whereIn('id', $collaboratorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'message' => 'Collaborators updated.',
            'collaborators' => $collaborators->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
        ]);
    }
}
