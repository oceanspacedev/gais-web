<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\InsuranceProvider;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsuranceProviderPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance::provider');
    }

    public function view(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('view_insurance::provider') && $this->visible($user, $insuranceProvider);
    }

    public function create(User $user): bool
    {
        return $user->can('create_insurance::provider');
    }

    public function update(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('update_insurance::provider') && $this->editable($user, $insuranceProvider);
    }

    public function delete(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('delete_insurance::provider') && $this->editable($user, $insuranceProvider);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_insurance::provider');
    }

    public function forceDelete(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('force_delete_insurance::provider') && $this->visible($user, $insuranceProvider);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_insurance::provider');
    }

    public function restore(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('restore_insurance::provider') && $this->visible($user, $insuranceProvider);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_insurance::provider');
    }

    public function replicate(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('replicate_insurance::provider') && $this->editable($user, $insuranceProvider);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_insurance::provider');
    }
}
