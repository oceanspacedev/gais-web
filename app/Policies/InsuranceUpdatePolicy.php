<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\InsuranceUpdate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pembaharuan polis mengikuti izin dan cakupan lihat induknya: melihat = lihat
 * asuransi, menambah/mengubah/menghapus/memulihkan = ubah asuransi.
 */
class InsuranceUpdatePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance');
    }

    public function view(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('view_insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function create(User $user): bool
    {
        return $user->can('update_insurance');
    }

    public function update(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('update_insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'insurance');
    }

    public function delete(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('update_insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'insurance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_insurance');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('force_delete_insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_insurance');
    }

    public function restore(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('update_insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('update_insurance');
    }
}
