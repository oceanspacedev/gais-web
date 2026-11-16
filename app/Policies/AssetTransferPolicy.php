<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\AssetTransfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetTransferPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AssetTransfer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('View:AssetTransfer') && $this->visible($user, $assetTransfer);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:AssetTransfer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('Update:AssetTransfer') && $this->editable($user, $assetTransfer);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('Delete:AssetTransfer') && $this->editable($user, $assetTransfer);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AssetTransfer');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('ForceDelete:AssetTransfer') && $this->visible($user, $assetTransfer);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:AssetTransfer');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('Restore:AssetTransfer') && $this->visible($user, $assetTransfer);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:AssetTransfer');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('Replicate:AssetTransfer') && $this->editable($user, $assetTransfer);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:AssetTransfer');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:AssetTransfer');
    }

    /**
     * Determine whether the user can make stock BAs (Serah Terima,
     * Pengembalian) on behalf of any general affair staff member.
     */
    public function manageStock(User $user): bool
    {
        return $user->can('ManageStock:AssetTransfer');
    }

    /**
     * Ubah jenis BA, pihak, dan aset pada BA yang sudah dibuat.
     */
    public function updateCore(User $user): bool
    {
        return $user->can('UpdateCore:AssetTransfer');
    }

    /**
     * Lepas satu riwayat transfer dari aset (BA utamanya tetap ada).
     */
    public function deleteDetail(User $user): bool
    {
        return $user->can('DeleteDetail:AssetTransfer');
    }
}
