<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\AssetSale;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Penjualan aset antar badan usaha. Siapa boleh menjual aset mana (akses ke
 * badan usaha penjual dan pembeli, aset di stok) diputuskan AssetSale::record();
 * di sini hanya izin Shield dan cakupan lihat.
 */
class AssetSalePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AssetSale');
    }

    public function view(User $user, AssetSale $assetSale): bool
    {
        return $user->can('View:AssetSale') && $this->visible($user, $assetSale);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AssetSale');
    }

    public function update(User $user, AssetSale $assetSale): bool
    {
        return $user->can('Update:AssetSale') && $this->editable($user, $assetSale);
    }

    public function delete(User $user, AssetSale $assetSale): bool
    {
        return $user->can('Delete:AssetSale') && $this->editable($user, $assetSale);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AssetSale');
    }

    public function forceDelete(User $user, AssetSale $assetSale): bool
    {
        return $user->can('ForceDelete:AssetSale') && $this->visible($user, $assetSale);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:AssetSale');
    }

    public function restore(User $user, AssetSale $assetSale): bool
    {
        return $user->can('Restore:AssetSale') && $this->visible($user, $assetSale);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:AssetSale');
    }

    public function replicate(User $user, AssetSale $assetSale): bool
    {
        return $user->can('Replicate:AssetSale') && $this->editable($user, $assetSale);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:AssetSale');
    }

    /**
     * Ekspor riwayat penjualan ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:AssetSale');
    }

    /**
     * Impor penjualan massal dari Excel bertemplate.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:AssetSale');
    }
}
