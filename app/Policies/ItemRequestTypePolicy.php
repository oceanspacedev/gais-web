<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestTypePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ItemRequestType');
    }

    public function view(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('View:ItemRequestType') && $this->visible($user, $itemRequestType);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ItemRequestType');
    }

    public function update(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('Update:ItemRequestType') && $this->editable($user, $itemRequestType);
    }

    public function delete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('Delete:ItemRequestType') && $this->editable($user, $itemRequestType);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ItemRequestType');
    }

    public function forceDelete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('ForceDelete:ItemRequestType') && $this->visible($user, $itemRequestType);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ItemRequestType');
    }

    public function restore(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('Restore:ItemRequestType') && $this->visible($user, $itemRequestType);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ItemRequestType');
    }

    public function replicate(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('Replicate:ItemRequestType') && $this->editable($user, $itemRequestType);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ItemRequestType');
    }
}
