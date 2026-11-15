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
        return $user->can('view_any_vehicle::insurance');
    }

    public function view(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('view_vehicle::insurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function create(User $user): bool
    {
        return $user->can('update_vehicle::insurance');
    }

    public function update(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('update_vehicle::insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function delete(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('update_vehicle::insurance') && $this->notTrashed($update) && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_vehicle::insurance');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen induknya.
     */
    public function forceDelete(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('force_delete_vehicle::insurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_vehicle::insurance');
    }

    public function restore(User $user, VehicleInsuranceUpdate $update): bool
    {
        return $user->can('update_vehicle::insurance') && $this->visibleParent($user, $update, 'vehicleInsurance');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('update_vehicle::insurance');
    }
}
