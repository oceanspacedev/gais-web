<?php

namespace App\Policies;

use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_item::request::type');
    }

    public function view(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('view_item::request::type');
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request::type');
    }

    public function update(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('update_item::request::type');
    }

    public function delete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('delete_item::request::type');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_item::request::type');
    }

    public function forceDelete(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('force_delete_item::request::type');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request::type');
    }

    public function restore(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('restore_item::request::type');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_item::request::type');
    }

    public function replicate(User $user, ItemRequestType $itemRequestType): bool
    {
        return $user->can('replicate_item::request::type');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_item::request::type');
    }
}
