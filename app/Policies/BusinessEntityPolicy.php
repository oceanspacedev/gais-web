<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BusinessEntityPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:BusinessEntity');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('View:BusinessEntity') && $this->visible($user, $businessEntity);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:BusinessEntity');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('Update:BusinessEntity') && $this->editable($user, $businessEntity);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('Delete:BusinessEntity') && $this->editable($user, $businessEntity);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:BusinessEntity');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('ForceDelete:BusinessEntity') && $this->visible($user, $businessEntity);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:BusinessEntity');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('Restore:BusinessEntity') && $this->visible($user, $businessEntity);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:BusinessEntity');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, BusinessEntity $businessEntity): bool
    {
        return $user->can('Replicate:BusinessEntity') && $this->editable($user, $businessEntity);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:BusinessEntity');
    }
}
