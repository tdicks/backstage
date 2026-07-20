<?php

namespace App\Http\Controllers;

use App\Models\Set;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
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
        $existingCollaboratorIds = $set->collaboratorUserIds();

        $validated = $request->validate([
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $rawIds = array_values(array_map('intval', $validated['collaborator_ids'] ?? []));

        // Remove the set owner from collaborators if mistakenly included.
        $collaboratorIds = array_values(array_filter($rawIds, fn (int $id) => $id !== $set->owner_id));

        $set->update(['collaborator_ids' => $collaboratorIds ?: null]);
        $set->loadMissing('session');

        $collaborators = User::query()
            ->whereIn('id', $collaboratorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $addedCollaboratorIds = array_values(array_diff($collaboratorIds, $existingCollaboratorIds));
        $removedCollaboratorIds = array_values(array_diff($existingCollaboratorIds, $collaboratorIds));

        $notificationService = app(NotificationService::class);
        $actor = $request->user();
        $actionUrl = route('sessions.show', $set->session).'#set-'.$set->id;

        if ($addedCollaboratorIds !== []) {
            $notificationService->notifyUsers(
                NotificationTypeCatalog::SET_COLLABORATOR_ADDED,
                User::query()->whereIn('id', $addedCollaboratorIds)->get(),
                $actor,
                [
                    'title' => 'Added as a collaborator',
                    'body' => $actor->name.' added you as a collaborator on '.$set->name.'.',
                    'action_url' => $actionUrl,
                    'action_label' => 'Open set',
                ]
            );
        }

        if ($removedCollaboratorIds !== []) {
            $notificationService->notifyUsers(
                NotificationTypeCatalog::SET_COLLABORATOR_REMOVED,
                User::query()->whereIn('id', $removedCollaboratorIds)->get(),
                $actor,
                [
                    'title' => 'Removed as a collaborator',
                    'body' => $actor->name.' removed you as a collaborator on '.$set->name.'.',
                    'action_url' => $actionUrl,
                    'action_label' => 'Open set',
                ]
            );
        }

        return response()->json([
            'message' => 'Collaborators updated.',
            'collaborators' => $collaborators->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
        ]);
    }
}
