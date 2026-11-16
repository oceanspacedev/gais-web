<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomAssetAttributePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CustomAssetAttribute');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('View:CustomAssetAttribute') && $this->visible($user, $customAssetAttribute);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:CustomAssetAttribute');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('Update:CustomAssetAttribute') && $this->editable($user, $customAssetAttribute);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('Delete:CustomAssetAttribute') && $this->editable($user, $customAssetAttribute) && ! $customAssetAttribute->isIdentity();
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:CustomAssetAttribute');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('ForceDelete:CustomAssetAttribute') && $this->visible($user, $customAssetAttribute);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:CustomAssetAttribute');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('Restore:CustomAssetAttribute') && $this->visible($user, $customAssetAttribute);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:CustomAssetAttribute');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CustomAssetAttribute $customAssetAttribute): bool
    {
        return $user->can('Replicate:CustomAssetAttribute') && $this->editable($user, $customAssetAttribute);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:CustomAssetAttribute');
    }
}
