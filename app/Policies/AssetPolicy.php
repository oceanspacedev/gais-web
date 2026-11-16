<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Asset');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Asset $asset): bool
    {
        return $user->can('View:Asset') && $this->visible($user, $asset);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Asset');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Asset $asset): bool
    {
        return $user->can('Update:Asset') && $this->editable($user, $asset);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('Delete:Asset') && $this->editable($user, $asset);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Asset');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Asset $asset): bool
    {
        return $user->can('ForceDelete:Asset') && $this->visible($user, $asset);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Asset');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Asset $asset): bool
    {
        return $user->can('Restore:Asset') && $this->visible($user, $asset);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Asset');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Asset $asset): bool
    {
        return $user->can('Replicate:Asset') && $this->editable($user, $asset);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Asset');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:Asset');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:Asset');
    }

    /**
     * Tandai dijual/rusak/hilang, selesaikan perbaikan, dan isi audit penjualan.
     */
    public function manageCondition(User $user): bool
    {
        return $user->can('ManageCondition:Asset');
    }

    public function repairValidation(User $user): bool
    {
        return $user->can('RepairValidation:Asset');
    }

    public function regenerateQr(User $user): bool
    {
        return $user->can('RegenerateQr:Asset');
    }

    /**
     * Ubah penerima dan badan usaha penerima langsung dari form aset.
     */
    public function updateRecipient(User $user): bool
    {
        return $user->can('UpdateRecipient:Asset');
    }

    public function merge(User $user): bool
    {
        return $user->can('Merge:Asset');
    }
}
