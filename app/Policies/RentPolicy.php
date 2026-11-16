<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Rent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RentPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Rent');
    }

    public function view(User $user, Rent $rent): bool
    {
        return $user->can('View:Rent') && $this->visible($user, $rent);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Rent');
    }

    public function update(User $user, Rent $rent): bool
    {
        return $user->can('Update:Rent') && $this->editable($user, $rent);
    }

    public function delete(User $user, Rent $rent): bool
    {
        return $user->can('Delete:Rent') && $this->editable($user, $rent);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Rent');
    }

    public function forceDelete(User $user, Rent $rent): bool
    {
        return $user->can('ForceDelete:Rent') && $this->visible($user, $rent);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Rent');
    }

    public function restore(User $user, Rent $rent): bool
    {
        return $user->can('Restore:Rent') && $this->visible($user, $rent);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Rent');
    }

    public function replicate(User $user, Rent $rent): bool
    {
        return $user->can('Replicate:Rent') && $this->editable($user, $rent);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Rent');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:Rent');
    }

    /**
     * Impor dari Excel.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:Rent');
    }
}
