<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ?User $model = null): bool
    {
        return $user->can('view_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ?User $model = null): bool
    {
        return $user->can('update_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ?User $model = null): bool
    {
        return $user->can('delete_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_user');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ?User $model = null): bool
    {
        return $user->can('force_delete_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_user');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ?User $model = null): bool
    {
        return $user->can('restore_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_user');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function replicate(User $user, ?User $model = null): bool
    {
        return $user->can('replicate_user') && $this->visibleUser($user, $model);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_user');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('import_user');
    }

    /**
     * Ekspor daftar user ke Excel (kolom ekspor sumo).
     */
    public function export(User $user): bool
    {
        return $user->can('export_user');
    }

    /**
     * Determine whether the user can impersonate other users.
     */
    public function impersonate(User $user): bool
    {
        return $user->can('impersonate_user');
    }

    /**
     * Username, email, password, role, dan nomor login WhatsApp user.
     */
    public function manageAccess(User $user): bool
    {
        return $user->can('manage_access_user');
    }

    /**
     * Badan usaha yang boleh diakses user lain.
     */
    public function manageBusinessEntityAccess(User $user): bool
    {
        return $user->can('manage_business_entity_access_user');
    }

    /**
     * Cakupan lihat user (User::visibility): akun sendiri, user di badan usaha
     * yang bisa diakses, dan akun super admin hanya untuk super admin. Cek izin
     * saja (tanpa record) tidak dibatasi.
     */
    private function visibleUser(User $user, ?User $model): bool
    {
        return $model === null || $this->visible($user, $model);
    }
}
