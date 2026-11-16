<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsurancePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Insurance');
    }

    public function view(User $user, Insurance $insurance): bool
    {
        return $user->can('View:Insurance') && $this->visible($user, $insurance);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Insurance');
    }

    public function update(User $user, Insurance $insurance): bool
    {
        return $user->can('Update:Insurance') && $this->editable($user, $insurance);
    }

    public function delete(User $user, Insurance $insurance): bool
    {
        return $user->can('Delete:Insurance') && $this->editable($user, $insurance);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Insurance');
    }

    public function forceDelete(User $user, Insurance $insurance): bool
    {
        return $user->can('ForceDelete:Insurance') && $this->visible($user, $insurance);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Insurance');
    }

    public function restore(User $user, Insurance $insurance): bool
    {
        return $user->can('Restore:Insurance') && $this->visible($user, $insurance);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Insurance');
    }

    public function replicate(User $user, Insurance $insurance): bool
    {
        return $user->can('Replicate:Insurance') && $this->editable($user, $insurance);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Insurance');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:Insurance');
    }

    /**
     * Impor dari Excel.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:Insurance');
    }
}
