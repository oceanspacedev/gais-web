<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\InsuranceScope;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsuranceScopePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:InsuranceScope');
    }

    public function view(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('View:InsuranceScope') && $this->visible($user, $insuranceScope);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:InsuranceScope');
    }

    public function update(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('Update:InsuranceScope') && $this->editable($user, $insuranceScope);
    }

    public function delete(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('Delete:InsuranceScope') && $this->editable($user, $insuranceScope);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:InsuranceScope');
    }

    public function forceDelete(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('ForceDelete:InsuranceScope') && $this->visible($user, $insuranceScope);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:InsuranceScope');
    }

    public function restore(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('Restore:InsuranceScope') && $this->visible($user, $insuranceScope);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:InsuranceScope');
    }

    public function replicate(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('Replicate:InsuranceScope') && $this->editable($user, $insuranceScope);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:InsuranceScope');
    }
}
