<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\InsuranceCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsuranceCategoryPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance::category');
    }

    public function view(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('view_insurance::category') && $this->visible($user, $insuranceCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('create_insurance::category');
    }

    public function update(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('update_insurance::category') && $this->editable($user, $insuranceCategory);
    }

    public function delete(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('delete_insurance::category') && $this->editable($user, $insuranceCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_insurance::category');
    }

    public function forceDelete(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('force_delete_insurance::category') && $this->visible($user, $insuranceCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_insurance::category');
    }

    public function restore(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('restore_insurance::category') && $this->visible($user, $insuranceCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_insurance::category');
    }

    public function replicate(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('replicate_insurance::category') && $this->editable($user, $insuranceCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_insurance::category');
    }
}
