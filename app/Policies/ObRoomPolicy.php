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
        return $user->can('ViewAny:ObRoom');
    }

    public function view(User $user, ObRoom $room): bool
    {
        return $user->can('View:ObRoom') && $this->visible($user, $room);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ObRoom');
    }

    public function update(User $user, ObRoom $room): bool
    {
        return $user->can('Update:ObRoom') && $this->editable($user, $room);
    }

    public function delete(User $user, ObRoom $room): bool
    {
        return $user->can('Delete:ObRoom') && $this->editable($user, $room);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ObRoom');
    }

    public function forceDelete(User $user, ObRoom $room): bool
    {
        return $user->can('ForceDelete:ObRoom') && $this->visible($user, $room);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ObRoom');
    }

    public function restore(User $user, ObRoom $room): bool
    {
        return $user->can('Restore:ObRoom') && $this->visible($user, $room);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ObRoom');
    }

    public function replicate(User $user, ObRoom $room): bool
    {
        return $user->can('Replicate:ObRoom') && $this->editable($user, $room);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ObRoom');
    }
}
