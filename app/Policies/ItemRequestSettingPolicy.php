<?php

namespace App\Policies;

use App\Models\ItemRequestSetting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_item::request::setting');
    }

    public function view(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('view_item::request::setting');
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request::setting');
    }

    public function update(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('update_item::request::setting');
    }

    public function delete(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('delete_item::request::setting');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_item::request::setting');
    }

    public function forceDelete(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('force_delete_item::request::setting');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request::setting');
    }

    public function restore(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('restore_item::request::setting');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_item::request::setting');
    }

    public function replicate(User $user, ItemRequestSetting $itemRequestSetting): bool
    {
        return $user->can('replicate_item::request::setting');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_item::request::setting');
    }
}
