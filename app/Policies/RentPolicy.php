<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Rent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RentPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_rent');
    }

    public function view(User $user, Rent $rent): bool
    {
        return $user->can('view_rent') && $this->visible($user, $rent);
    }

    public function create(User $user): bool
    {
        return $user->can('create_rent');
    }

    public function update(User $user, Rent $rent): bool
    {
        return $user->can('update_rent') && $this->editable($user, $rent);
    }

    public function delete(User $user, Rent $rent): bool
    {
        return $user->can('delete_rent') && $this->editable($user, $rent);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_rent');
    }

    public function forceDelete(User $user, Rent $rent): bool
    {
        return $user->can('force_delete_rent') && $this->visible($user, $rent);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_rent');
    }

    public function restore(User $user, Rent $rent): bool
    {
        return $user->can('restore_rent') && $this->visible($user, $rent);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_rent');
    }

    public function replicate(User $user, Rent $rent): bool
    {
        return $user->can('replicate_rent') && $this->editable($user, $rent);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_rent');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_rent');
    }

    /**
     * Impor dari Excel.
     */
    public function import(User $user): bool
    {
        return $user->can('import_rent');
    }
}
