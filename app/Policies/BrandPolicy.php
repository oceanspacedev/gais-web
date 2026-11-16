<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BrandPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Brand');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $user->can('View:Brand') && $this->visible($user, $brand);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Brand');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $user->can('Update:Brand') && $this->editable($user, $brand);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('Delete:Brand') && $this->editable($user, $brand);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Brand');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Brand $brand): bool
    {
        return $user->can('ForceDelete:Brand') && $this->visible($user, $brand);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Brand');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Brand $brand): bool
    {
        return $user->can('Restore:Brand') && $this->visible($user, $brand);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Brand');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Brand $brand): bool
    {
        return $user->can('Replicate:Brand') && $this->editable($user, $brand);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Brand');
    }
}
