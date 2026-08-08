<?php

namespace App\Policies;

use App\Models\Set;
use App\Models\User;

class SetPolicy
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
    public function view(User $user, Set $set): bool
    {
        return $user->is_admin || ! $set->is_hidden || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Set $set): bool
    {
        return $user->is_admin
            || ($set->owner_id === $user->id && (int) $set->deleted_by_user_id === (int) $user->id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can manage a planned set.
     */
    public function managePlanned(User $user, Set $set): bool
    {
        if (! $set->isDraft()) {
            return false;
        }

        return $user->is_admin || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    /**
     * Determine whether the user can update availability for a planned set.
     */
    public function voteAvailability(User $user, Set $set): bool
    {
        return $set->isDraft() && $this->view($user, $set);
    }

    /**
     * Determine whether the user can schedule a planned set into a jam session.
     */
    public function schedule(User $user, Set $set): bool
    {
        return $this->managePlanned($user, $set);
    }

    /**
     * Determine whether the user can unschedule a set back into planned sets.
     */
    public function unschedule(User $user, Set $set): bool
    {
        if ($set->isDraft() || $set->jam_session_id === null) {
            return false;
        }

        return $user->is_admin || $set->owner_id === $user->id || $set->isCollaborator($user);
    }
}
