<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendorPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Vendor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vendor $vendor): bool
    {
        return $user->can('View:Vendor') && $this->visible($user, $vendor);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Vendor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vendor $vendor): bool
    {
        return $user->can('Update:Vendor') && $this->editable($user, $vendor);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->can('Delete:Vendor') && $this->editable($user, $vendor);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Vendor');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Vendor $vendor): bool
    {
        return $user->can('ForceDelete:Vendor') && $this->visible($user, $vendor);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Vendor');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Vendor $vendor): bool
    {
        return $user->can('Restore:Vendor') && $this->visible($user, $vendor);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Vendor');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Vendor $vendor): bool
    {
        return $user->can('Replicate:Vendor') && $this->editable($user, $vendor);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Vendor');
    }
}
