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
        return $user->can('ViewAny:VehicleInsurance');
    }

    public function view(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('View:VehicleInsurance') && $this->visible($user, $insurance);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:VehicleInsurance');
    }

    public function update(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('Update:VehicleInsurance') && $this->editable($user, $insurance);
    }

    public function delete(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('Delete:VehicleInsurance') && $this->editable($user, $insurance);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:VehicleInsurance');
    }

    public function forceDelete(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('ForceDelete:VehicleInsurance') && $this->visible($user, $insurance);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:VehicleInsurance');
    }

    public function restore(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('Restore:VehicleInsurance') && $this->visible($user, $insurance);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:VehicleInsurance');
    }

    public function replicate(User $user, VehicleInsurance $insurance): bool
    {
        return $user->can('Replicate:VehicleInsurance') && $this->editable($user, $insurance);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:VehicleInsurance');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:VehicleInsurance');
    }
}
