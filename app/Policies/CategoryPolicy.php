<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Category');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->can('View:Category') && $this->visible($user, $category);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Category');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can('Update:Category') && $this->editable($user, $category);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('Delete:Category') && $this->editable($user, $category);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Category');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return $user->can('ForceDelete:Category') && $this->visible($user, $category);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Category');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->can('Restore:Category') && $this->visible($user, $category);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Category');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Category $category): bool
    {
        return $user->can('Replicate:Category') && $this->editable($user, $category);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Category');
    }
}
