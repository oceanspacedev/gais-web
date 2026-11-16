<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ObShift;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObShiftPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ObShift');
    }

    public function view(User $user, ObShift $shift): bool
    {
        return $user->can('View:ObShift') && $this->visible($user, $shift);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ObShift');
    }

    public function update(User $user, ObShift $shift): bool
    {
        return $user->can('Update:ObShift') && $this->editable($user, $shift);
    }

    public function delete(User $user, ObShift $shift): bool
    {
        return $user->can('Delete:ObShift') && $this->editable($user, $shift);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ObShift');
    }

    public function forceDelete(User $user, ObShift $shift): bool
    {
        return $user->can('ForceDelete:ObShift') && $this->visible($user, $shift);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ObShift');
    }

    public function restore(User $user, ObShift $shift): bool
    {
        return $user->can('Restore:ObShift') && $this->visible($user, $shift);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ObShift');
    }

    public function replicate(User $user, ObShift $shift): bool
    {
        return $user->can('Replicate:ObShift') && $this->editable($user, $shift);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ObShift');
    }
}
