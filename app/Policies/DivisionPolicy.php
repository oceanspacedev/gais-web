<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Division;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DivisionPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Division');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Division $division): bool
    {
        return $user->can('View:Division') && $this->visible($user, $division);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Division');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Division $division): bool
    {
        return $user->can('Update:Division') && $this->editable($user, $division);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Division $division): bool
    {
        return $user->can('Delete:Division') && $this->editable($user, $division);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Division');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Division $division): bool
    {
        return $user->can('ForceDelete:Division') && $this->visible($user, $division);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Division');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Division $division): bool
    {
        return $user->can('Restore:Division') && $this->visible($user, $division);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Division');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Division $division): bool
    {
        return $user->can('Create:Division') && $this->editable($user, $division);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Division');
    }
}
