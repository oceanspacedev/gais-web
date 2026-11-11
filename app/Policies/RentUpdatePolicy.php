<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\RentUpdate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pembaharuan perjanjian sewa mengikuti izin dan cakupan lihat induknya: melihat = lihat
 * sewa, menambah/mengubah/menghapus/memulihkan = ubah sewa.
 */
class RentUpdatePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_rent');
    }

    public function view(User $user, RentUpdate $update): bool
    {
        return $user->can('view_rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function create(User $user): bool
    {
        return $user->can('update_rent');
    }

    public function update(User $user, RentUpdate $update): bool
    {
        return $user->can('update_rent') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'rent');
    }

    public function delete(User $user, RentUpdate $update): bool
    {
        return $user->can('update_rent') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'rent');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_rent');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, RentUpdate $update): bool
    {
        return $user->can('force_delete_rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_rent');
    }

    public function restore(User $user, RentUpdate $update): bool
    {
        return $user->can('update_rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('update_rent');
    }
}
