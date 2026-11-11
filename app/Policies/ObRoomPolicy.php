<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ObRoom;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObRoomPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ob::room');
    }

    public function view(User $user, ObRoom $room): bool
    {
        return $user->can('view_ob::room') && $this->visible($user, $room);
    }

    public function create(User $user): bool
    {
        return $user->can('create_ob::room');
    }

    public function update(User $user, ObRoom $room): bool
    {
        return $user->can('update_ob::room') && $this->editable($user, $room);
    }

    public function delete(User $user, ObRoom $room): bool
    {
        return $user->can('delete_ob::room') && $this->editable($user, $room);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::room');
    }

    public function forceDelete(User $user, ObRoom $room): bool
    {
        return $user->can('force_delete_ob::room') && $this->visible($user, $room);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ob::room');
    }

    public function restore(User $user, ObRoom $room): bool
    {
        return $user->can('restore_ob::room') && $this->visible($user, $room);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ob::room');
    }

    public function replicate(User $user, ObRoom $room): bool
    {
        return $user->can('replicate_ob::room') && $this->editable($user, $room);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_ob::room');
    }
}
