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
        return $user->can('view_any_item::request::type');
    }

    public function view(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('view_item::request::type') && $this->visible($user, $itemRequestType);
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request::type');
    }

    public function update(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('update_item::request::type') && $this->editable($user, $itemRequestType);
    }

    public function delete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('delete_item::request::type') && $this->editable($user, $itemRequestType);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_item::request::type');
    }

    public function forceDelete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('force_delete_item::request::type') && $this->visible($user, $itemRequestType);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request::type');
    }

    public function restore(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('restore_item::request::type') && $this->visible($user, $itemRequestType);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_item::request::type');
    }

    public function replicate(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('replicate_item::request::type') && $this->editable($user, $itemRequestType);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_item::request::type');
    }
}
