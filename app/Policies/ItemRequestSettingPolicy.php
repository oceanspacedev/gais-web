<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ItemRequestSetting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestSettingPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ItemRequestSetting');
    }

    public function view(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('View:ItemRequestSetting') && $this->visible($user, $itemRequestSetting);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ItemRequestSetting');
    }

    public function update(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('Update:ItemRequestSetting') && $this->editable($user, $itemRequestSetting);
    }

    public function delete(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('Delete:ItemRequestSetting') && $this->editable($user, $itemRequestSetting);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ItemRequestSetting');
    }

    public function forceDelete(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('ForceDelete:ItemRequestSetting') && $this->visible($user, $itemRequestSetting);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ItemRequestSetting');
    }

    public function restore(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('Restore:ItemRequestSetting') && $this->visible($user, $itemRequestSetting);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ItemRequestSetting');
    }

    public function replicate(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('Replicate:ItemRequestSetting') && $this->editable($user, $itemRequestSetting);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ItemRequestSetting');
    }
}
