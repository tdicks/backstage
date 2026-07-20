<?php

namespace App\Policies;

use App\Models\Set;
use App\Models\Song;
use App\Models\User;
class SongPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Song $song): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Song $song): bool
    {
        return $user->is_admin || $song->set->owner_id === $user->id || $song->set->isCollaborator($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Song $song): bool
    {
        return $user->is_admin || $song->set->owner_id === $user->id || $song->set->isCollaborator($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Song $song): bool
    {
        return $user->is_admin || $song->set->owner_id === $user->id || $song->set->isCollaborator($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Song $song): bool
    {
        return $user->is_admin || $song->set->owner_id === $user->id || $song->set->isCollaborator($user);
    }
}
