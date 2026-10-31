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
        return $user->can('view_any_item::request::log');
    }

    public function view(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('view_item::request::log') && $this->visible($user, $itemRequestLog);
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request::log');
    }

    public function update(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('update_item::request::log') && $this->editable($user, $itemRequestLog);
    }

    public function delete(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('delete_item::request::log') && $this->editable($user, $itemRequestLog);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_item::request::log');
    }

    public function forceDelete(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('force_delete_item::request::log') && $this->visible($user, $itemRequestLog);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request::log');
    }

    public function restore(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('restore_item::request::log') && $this->visible($user, $itemRequestLog);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_item::request::log');
    }

    public function replicate(User $user, ItemRequestLog $itemRequestLog): bool
    {
        return $user->can('replicate_item::request::log') && $this->editable($user, $itemRequestLog);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_item::request::log');
    }
}
