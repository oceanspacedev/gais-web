<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ObChecksheet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObChecksheetPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ObChecksheet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('View:ObChecksheet') && $this->visible($user, $obChecksheet);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:ObChecksheet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('Update:ObChecksheet')
            && $this->editable($user, $obChecksheet)
            && ($this->isOwnedBy($user, $obChecksheet) || $this->updateAll($user));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('Delete:ObChecksheet')
            && $this->editable($user, $obChecksheet)
            && ($this->isOwnedBy($user, $obChecksheet) || $this->deleteAll($user));
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ObChecksheet');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('ForceDelete:ObChecksheet') && $this->visible($user, $obChecksheet);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ObChecksheet');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('Restore:ObChecksheet') && $this->visible($user, $obChecksheet);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ObChecksheet');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('Replicate:ObChecksheet') && $this->editable($user, $obChecksheet);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ObChecksheet');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:ObChecksheet');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:ObChecksheet');
    }

    /**
     * Checksheet petugas OB lain; tanpa izin ini user hanya melihat miliknya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('ViewAll:ObChecksheet');
    }

    public function updateAll(User $user): bool
    {
        return $user->can('UpdateAll:ObChecksheet');
    }

    public function deleteAll(User $user): bool
    {
        return $user->can('DeleteAll:ObChecksheet');
    }

    /**
     * Atasan menugaskan pembersihan ke OB dan mengganti petugasnya.
     */
    public function assign(User $user): bool
    {
        return $user->can('Assign:ObChecksheet');
    }

    /**
     * Checksheet tanpa petugas (atau model yang belum tersimpan) cukup dicek izinnya.
     */
    private function isOwnedBy(User $user, ObChecksheet $obChecksheet): bool
    {
        return blank($obChecksheet->user_id) || $obChecksheet->user_id === $user->id;
    }
}
