<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitTypePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:UnitType');
    }

    public function view(User $user, UnitType $unitType): bool
    {
        return $user->can('View:UnitType') && $this->visible($user, $unitType);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:UnitType');
    }

    public function update(User $user, UnitType $unitType): bool
    {
        return $user->can('Update:UnitType') && $this->editable($user, $unitType);
    }

    public function delete(User $user, UnitType $unitType): bool
    {
        return $user->can('Delete:UnitType') && $this->editable($user, $unitType);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:UnitType');
    }

    public function forceDelete(User $user, UnitType $unitType): bool
    {
        return $user->can('ForceDelete:UnitType') && $this->visible($user, $unitType);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:UnitType');
    }

    public function restore(User $user, UnitType $unitType): bool
    {
        return $user->can('Restore:UnitType') && $this->visible($user, $unitType);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:UnitType');
    }

    public function replicate(User $user, UnitType $unitType): bool
    {
        return $user->can('Replicate:UnitType') && $this->editable($user, $unitType);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:UnitType');
    }
}
