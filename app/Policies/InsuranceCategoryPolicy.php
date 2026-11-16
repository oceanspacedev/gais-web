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
        return $user->can('ViewAny:InsuranceCategory');
    }

    public function view(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('View:InsuranceCategory') && $this->visible($user, $insuranceCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:InsuranceCategory');
    }

    public function update(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('Update:InsuranceCategory') && $this->editable($user, $insuranceCategory);
    }

    public function delete(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('Delete:InsuranceCategory') && $this->editable($user, $insuranceCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:InsuranceCategory');
    }

    public function forceDelete(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('ForceDelete:InsuranceCategory') && $this->visible($user, $insuranceCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:InsuranceCategory');
    }

    public function restore(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('Restore:InsuranceCategory') && $this->visible($user, $insuranceCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:InsuranceCategory');
    }

    public function replicate(User $user, InsuranceCategory $insuranceCategory): bool
    {
        return $user->can('Replicate:InsuranceCategory') && $this->editable($user, $insuranceCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:InsuranceCategory');
    }
}
