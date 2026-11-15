<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobTitlePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:JobTitle');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('View:JobTitle') && $this->visible($user, $jobTitle);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:JobTitle');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('Update:JobTitle') && $this->editable($user, $jobTitle);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('Delete:JobTitle') && $this->editable($user, $jobTitle);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:JobTitle');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('ForceDelete:JobTitle') && $this->visible($user, $jobTitle);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:JobTitle');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('Restore:JobTitle') && $this->visible($user, $jobTitle);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:JobTitle');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('Replicate:JobTitle') && $this->editable($user, $jobTitle);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:JobTitle');
    }
}
