<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\User;
use App\Models\VehicleInsuranceUpdate;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pembaharuan polis kendaraan mengikuti izin dan cakupan lihat induknya: melihat = lihat
 * asuransi, menambah/mengubah/menghapus/memulihkan = ubah asuransi.
 */
class VehicleInsuranceUpdatePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:VehicleInsurance');
    }

    public function view(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('View:VehicleInsurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function create(User $user): bool
    {
        return $user->can('Update:VehicleInsurance');
    }

    public function update(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('Update:VehicleInsurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function delete(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('Update:VehicleInsurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Update:VehicleInsurance');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('ForceDelete:VehicleInsurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:VehicleInsurance');
    }

    public function restore(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('Update:VehicleInsurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('Update:VehicleInsurance');
    }
}
