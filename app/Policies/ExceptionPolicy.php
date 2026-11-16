<?php

namespace App\Policies;

use App\Models\User;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExceptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Exception');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Exception $exception): bool
    {
        return $user->can('View:Exception');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Exception');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Exception $exception): bool
    {
        return $user->can('Update:Exception');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Exception $exception): bool
    {
        return $user->can('Delete:Exception');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Exception');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Exception $exception): bool
    {
        return $user->can('ForceDelete:Exception');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Exception');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Exception $exception): bool
    {
        return $user->can('Restore:Exception');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Exception');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Exception $exception): bool
    {
        return $user->can('Replicate:Exception');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Exception');
    }
}
