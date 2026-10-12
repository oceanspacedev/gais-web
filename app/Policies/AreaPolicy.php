<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AreaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_area');
    }

    public function view(User $user, Area $area): bool
    {
        return $user->can('view_area');
    }

    public function create(User $user): bool
    {
        return $user->can('create_area');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->can('update_area');
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->can('delete_area');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_area');
    }

    public function forceDelete(User $user, Area $area): bool
    {
        return $user->can('force_delete_area');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_area');
    }

    public function restore(User $user, Area $area): bool
    {
        return $user->can('restore_area');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_area');
    }

    public function replicate(User $user, Area $area): bool
    {
        return $user->can('replicate_area');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_area');
    }
}
