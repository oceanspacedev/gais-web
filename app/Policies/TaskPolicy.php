<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Task');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can('View:Task') && $this->visible($user, $task);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Task');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->can('Update:Task') && $this->editable($user, $task);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->can('Delete:Task') && $this->editable($user, $task);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Task');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $user->can('ForceDelete:Task') && $this->visible($user, $task);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Task');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Task $task): bool
    {
        return $user->can('Restore:Task') && $this->visible($user, $task);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Task');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Task $task): bool
    {
        return $user->can('Replicate:Task') && $this->editable($user, $task);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Task');
    }
}
