<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Area;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AreaPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Area');
    }

    public function view(User $user, Area $area): bool
    {
        return $user->can('View:Area') && $this->visible($user, $area);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Area');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->can('Update:Area') && $this->editable($user, $area);
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->can('Delete:Area') && $this->editable($user, $area);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Area');
    }

    public function forceDelete(User $user, Area $area): bool
    {
        return $user->can('ForceDelete:Area') && $this->visible($user, $area);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Area');
    }

    public function restore(User $user, Area $area): bool
    {
        return $user->can('Restore:Area') && $this->visible($user, $area);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Area');
    }

    public function replicate(User $user, Area $area): bool
    {
        return $user->can('Replicate:Area') && $this->editable($user, $area);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Area');
    }
}
