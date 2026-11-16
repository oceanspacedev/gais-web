<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\User;
use App\Models\VehicleChecksheet;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehicleChecksheetPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:VehicleChecksheet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('View:VehicleChecksheet') && $this->visible($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:VehicleChecksheet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('Update:VehicleChecksheet') && $this->editable($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('Delete:VehicleChecksheet') && $this->editable($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:VehicleChecksheet');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('ForceDelete:VehicleChecksheet') && $this->visible($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:VehicleChecksheet');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('Restore:VehicleChecksheet') && $this->visible($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:VehicleChecksheet');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('Replicate:VehicleChecksheet') && $this->editable($user, $vehicleChecksheet);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Reorder:VehicleChecksheet');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:VehicleChecksheet');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:VehicleChecksheet');
    }
}
