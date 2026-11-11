<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsurancePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance');
    }

    public function view(User $user, Insurance $insurance): bool
    {
        return $user->can('view_insurance') && $this->visible($user, $insurance);
    }

    public function create(User $user): bool
    {
        return $user->can('create_insurance');
    }

    public function update(User $user, Insurance $insurance): bool
    {
        return $user->can('update_insurance') && $this->editable($user, $insurance);
    }

    public function delete(User $user, Insurance $insurance): bool
    {
        return $user->can('delete_insurance') && $this->editable($user, $insurance);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_insurance');
    }

    public function forceDelete(User $user, Insurance $insurance): bool
    {
        return $user->can('force_delete_insurance') && $this->visible($user, $insurance);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_insurance');
    }

    public function restore(User $user, Insurance $insurance): bool
    {
        return $user->can('restore_insurance') && $this->visible($user, $insurance);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_insurance');
    }

    public function replicate(User $user, Insurance $insurance): bool
    {
        return $user->can('replicate_insurance') && $this->editable($user, $insurance);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_insurance');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_insurance');
    }

    /**
     * Impor dari Excel.
     */
    public function import(User $user): bool
    {
        return $user->can('import_insurance');
    }
}
