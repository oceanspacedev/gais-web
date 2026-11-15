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
        return $user->can('ViewAny:Rent');
    }

    public function view(User $user, RentUpdate $update): bool
    {
        return $user->can('View:Rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function create(User $user): bool
    {
        return $user->can('Update:Rent');
    }

    public function update(User $user, RentUpdate $update): bool
    {
        return $user->can('Update:Rent') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'rent');
    }

    public function delete(User $user, RentUpdate $update): bool
    {
        return $user->can('Update:Rent') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'rent');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Update:Rent');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, RentUpdate $update): bool
    {
        return $user->can('ForceDelete:Rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Rent');
    }

    public function restore(User $user, RentUpdate $update): bool
    {
        return $user->can('Update:Rent') && $this->visibleParent($user, $update, 'rent');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('Update:Rent');
    }
}
