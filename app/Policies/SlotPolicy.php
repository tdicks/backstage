<?php

namespace App\Policies;

use App\Models\Slot;
use App\Models\User;
class SlotPolicy
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
    public function view(User $user, Slot $slot): bool
    {
        return true;
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
    public function update(User $user, Slot $slot): bool
    {
        return $user->is_admin || $slot->song->set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Slot $slot): bool
    {
        return $user->is_admin || $slot->song->set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Slot $slot): bool
    {
        return $user->is_admin || $slot->song->set->owner_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Slot $slot): bool
    {
        return $user->is_admin || $slot->song->set->owner_id === $user->id;
    }
}
