<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\SecurityChecksheet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SecurityChecksheetPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:SecurityChecksheet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('View:SecurityChecksheet') && $this->visible($user, $securityChecksheet);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:SecurityChecksheet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('Update:SecurityChecksheet')
            && $this->editable($user, $securityChecksheet)
            && ($this->isOwnedBy($user, $securityChecksheet) || $this->updateAll($user));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('Delete:SecurityChecksheet')
            && $this->editable($user, $securityChecksheet)
            && ($this->isOwnedBy($user, $securityChecksheet) || $this->deleteAll($user));
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:SecurityChecksheet');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('ForceDelete:SecurityChecksheet') && $this->visible($user, $securityChecksheet);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:SecurityChecksheet');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('Restore:SecurityChecksheet') && $this->visible($user, $securityChecksheet);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:SecurityChecksheet');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return $user->can('Replicate:SecurityChecksheet') && $this->editable($user, $securityChecksheet);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:SecurityChecksheet');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:SecurityChecksheet');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:SecurityChecksheet');
    }

    /**
     * Laporan petugas security lain; tanpa izin ini user hanya melihat miliknya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('ViewAll:SecurityChecksheet');
    }

    public function updateAll(User $user): bool
    {
        return $user->can('UpdateAll:SecurityChecksheet');
    }

    public function deleteAll(User $user): bool
    {
        return $user->can('DeleteAll:SecurityChecksheet');
    }

    /**
     * Checksheet tanpa petugas (atau model yang belum tersimpan) cukup dicek izinnya.
     */
    private function isOwnedBy(User $user, SecurityChecksheet $securityChecksheet): bool
    {
        return blank($securityChecksheet->user_id) || $securityChecksheet->user_id === $user->id;
    }
}
