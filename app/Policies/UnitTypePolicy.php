<?php

namespace App\Policies;

use App\Models\UnitType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_unit::type');
    }

    public function view(User $user, UnitType $unitType): bool
    {
        return $user->can('view_unit::type');
    }

    public function create(User $user): bool
    {
        return $user->can('create_unit::type');
    }

    public function update(User $user, UnitType $unitType): bool
    {
        return $user->can('update_unit::type');
    }

    public function delete(User $user, UnitType $unitType): bool
    {
        return $user->can('delete_unit::type');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_unit::type');
    }

    public function forceDelete(User $user, UnitType $unitType): bool
    {
        return $user->can('force_delete_unit::type');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_unit::type');
    }

    public function restore(User $user, UnitType $unitType): bool
    {
        return $user->can('restore_unit::type');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_unit::type');
    }

    public function replicate(User $user, UnitType $unitType): bool
    {
        return $user->can('replicate_unit::type');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_unit::type');
    }
}
