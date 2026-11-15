<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\User;
use App\Models\VehicleInsurance;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Asuransi kendaraan: izin terpisah dari asuransi bangunan (subjek vehicle::insurance).
 */
class VehicleInsurancePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_vehicle::insurance');
    }

    public function view(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('view_vehicle::insurance') && $this->visible($user, $insurance);
    }

    public function create(User $user): bool
    {
        return $user->can('create_vehicle::insurance');
    }

    public function update(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('update_vehicle::insurance') && $this->editable($user, $insurance);
    }

    public function delete(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('delete_vehicle::insurance') && $this->editable($user, $insurance);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_vehicle::insurance');
    }

    public function forceDelete(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('force_delete_vehicle::insurance') && $this->visible($user, $insurance);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_vehicle::insurance');
    }

    public function restore(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('restore_vehicle::insurance') && $this->visible($user, $insurance);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_vehicle::insurance');
    }

    public function replicate(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('replicate_vehicle::insurance') && $this->editable($user, $insurance);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_vehicle::insurance');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_vehicle::insurance');
    }
}
