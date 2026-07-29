<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Set;
use App\Models\Slot;
use App\Models\Song;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return $this->canViewAttachable($user, $attachment->attachable);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function createForSet(User $user, Set $set): bool
    {
        return $this->canManageSetAttachments($user, $set);
    }

    public function createForSong(User $user, Song $song): bool
    {
        return $this->canManageSongAttachments($user, $song);
    }

    public function createForSlot(User $user, Slot $slot): bool
    {
        return $this->canManageSlotAttachments($user, $slot);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attachment $attachment): bool
    {
        return $this->canManageAttachable($user, $attachment->attachable);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->canManageAttachable($user, $attachment->attachable);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return $this->canManageAttachable($user, $attachment->attachable);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $this->canManageAttachable($user, $attachment->attachable);
    }

    private function canViewAttachable(User $user, mixed $attachable): bool
    {
        if ($attachable instanceof Set) {
            return $this->canViewSet($user, $attachable);
        }

        if ($attachable instanceof Song) {
            $attachable->loadMissing('set');

            return $this->canViewSet($user, $attachable->set);
        }

        if ($attachable instanceof Slot) {
            $attachable->loadMissing('song.set');

            return $this->canViewSet($user, $attachable->song->set);
        }

        return false;
    }

    private function canManageAttachable(User $user, mixed $attachable): bool
    {
        if ($attachable instanceof Set) {
            return $this->canManageSetAttachments($user, $attachable);
        }

        if ($attachable instanceof Song) {
            return $this->canManageSongAttachments($user, $attachable);
        }

        if ($attachable instanceof Slot) {
            return $this->canManageSlotAttachments($user, $attachable);
        }

        return false;
    }

    private function canViewSet(User $user, Set $set): bool
    {
        return $user->is_admin || ! $set->is_hidden || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    private function canManageSetAttachments(User $user, Set $set): bool
    {
        return $user->is_admin || $set->owner_id === $user->id || $set->isCollaborator($user);
    }

    private function canManageSongAttachments(User $user, Song $song): bool
    {
        $song->loadMissing('set', 'slots');

        return $this->canManageSetAttachments($user, $song->set)
            || $song->slots->contains(fn (Slot $slot) => (int) $slot->user_id === (int) $user->id);
    }

    private function canManageSlotAttachments(User $user, Slot $slot): bool
    {
        $slot->loadMissing('song.set');

        return $this->canManageSetAttachments($user, $slot->song->set)
            || (int) $slot->user_id === (int) $user->id;
    }
}
