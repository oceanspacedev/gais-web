<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ItemRequestLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestLogPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ItemRequestLog');
    }

    public function view(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('View:ItemRequestLog') && $this->visible($user, $itemRequestLog);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ItemRequestLog');
    }

    public function update(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('Update:ItemRequestLog') && $this->editable($user, $itemRequestLog);
    }

    public function delete(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('Delete:ItemRequestLog') && $this->editable($user, $itemRequestLog);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ItemRequestLog');
    }

    public function forceDelete(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('ForceDelete:ItemRequestLog') && $this->visible($user, $itemRequestLog);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ItemRequestLog');
    }

    public function restore(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('Restore:ItemRequestLog') && $this->visible($user, $itemRequestLog);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ItemRequestLog');
    }

    public function replicate(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('Replicate:ItemRequestLog') && $this->editable($user, $itemRequestLog);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ItemRequestLog');
    }
}
