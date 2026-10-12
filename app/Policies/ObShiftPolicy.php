<?php

namespace App\Policies;

use App\Models\ObShift;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObShiftPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ob::shift');
    }

    public function view(User $user, ObShift $shift): bool
    {
        return $user->can('view_ob::shift');
    }

    public function create(User $user): bool
    {
        return $user->can('create_ob::shift');
    }

    public function update(User $user, ObShift $shift): bool
    {
        return $user->can('update_ob::shift');
    }

    public function delete(User $user, ObShift $shift): bool
    {
        return $user->can('delete_ob::shift');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::shift');
    }
}
