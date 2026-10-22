<?php

namespace App\Policies;

use App\Models\ObRoom;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObRoomPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ob::room');
    }

    public function view(User $user, ObRoom $room): bool
    {
        return $user->can('view_ob::room');
    }

    public function create(User $user): bool
    {
        return $user->can('create_ob::room');
    }

    public function update(User $user, ObRoom $room): bool
    {
        return $user->can('update_ob::room');
    }

    public function delete(User $user, ObRoom $room): bool
    {
        return $user->can('delete_ob::room');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::room');
    }
}
