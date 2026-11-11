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
        return $user->can('view_any_asset::sale');
    }

    public function view(User $user, AssetSale $assetSale): bool
    {
        return $user->can('view_asset::sale') && $this->visible($user, $assetSale);
    }

    public function create(User $user): bool
    {
        return $user->can('create_asset::sale');
    }

    public function update(User $user, AssetSale $assetSale): bool
    {
        return $user->can('update_asset::sale') && $this->editable($user, $assetSale);
    }

    public function delete(User $user, AssetSale $assetSale): bool
    {
        return $user->can('delete_asset::sale') && $this->editable($user, $assetSale);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::sale');
    }

    public function forceDelete(User $user, AssetSale $assetSale): bool
    {
        return $user->can('force_delete_asset::sale') && $this->visible($user, $assetSale);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_asset::sale');
    }

    public function restore(User $user, AssetSale $assetSale): bool
    {
        return $user->can('restore_asset::sale') && $this->visible($user, $assetSale);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_asset::sale');
    }

    public function replicate(User $user, AssetSale $assetSale): bool
    {
        return $user->can('replicate_asset::sale') && $this->editable($user, $assetSale);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_asset::sale');
    }

    /**
     * Ekspor riwayat penjualan ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_asset::sale');
    }

    /**
     * Impor penjualan massal dari Excel bertemplate.
     */
    public function import(User $user): bool
    {
        return $user->can('import_asset::sale');
    }
}
