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
        return $user->can('view_any_ob::shift');
    }

    public function view(User $user, ObShift $shift): bool
    {
        return $user->can('view_ob::shift') && $this->visible($user, $shift);
    }

    public function create(User $user): bool
    {
        return $user->can('create_ob::shift');
    }

    public function update(User $user, ObShift $shift): bool
    {
        return $user->can('update_ob::shift') && $this->editable($user, $shift);
    }

    public function delete(User $user, ObShift $shift): bool
    {
        return $user->can('delete_ob::shift') && $this->editable($user, $shift);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::shift');
    }

    public function forceDelete(User $user, ObShift $shift): bool
    {
        return $user->can('force_delete_ob::shift') && $this->visible($user, $shift);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ob::shift');
    }

    public function restore(User $user, ObShift $shift): bool
    {
        return $user->can('restore_ob::shift') && $this->visible($user, $shift);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ob::shift');
    }

    public function replicate(User $user, ObShift $shift): bool
    {
        return $user->can('replicate_ob::shift') && $this->editable($user, $shift);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_ob::shift');
    }
}
