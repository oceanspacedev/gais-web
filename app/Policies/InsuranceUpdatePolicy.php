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
        return $user->can('ViewAny:Insurance');
    }

    public function view(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('View:Insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function create(User $user): bool
    {
        return $user->can('Update:Insurance');
    }

    public function update(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('Update:Insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'insurance');
    }

    public function delete(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('Update:Insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'insurance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Update:Insurance');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('ForceDelete:Insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Insurance');
    }

    public function restore(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('Update:Insurance') && $this->visibleParent($user, $update, 'insurance');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('Update:Insurance');
    }
}
