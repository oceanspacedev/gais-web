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
        return $user->can('ViewAny:InsuranceProvider');
    }

    public function view(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('View:InsuranceProvider') && $this->visible($user, $insuranceProvider);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:InsuranceProvider');
    }

    public function update(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('Update:InsuranceProvider') && $this->editable($user, $insuranceProvider);
    }

    public function delete(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('Delete:InsuranceProvider') && $this->editable($user, $insuranceProvider);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:InsuranceProvider');
    }

    public function forceDelete(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('ForceDelete:InsuranceProvider') && $this->visible($user, $insuranceProvider);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:InsuranceProvider');
    }

    public function restore(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('Restore:InsuranceProvider') && $this->visible($user, $insuranceProvider);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:InsuranceProvider');
    }

    public function replicate(User $user, InsuranceProvider $insuranceProvider): bool
    {
        return $user->can('Replicate:InsuranceProvider') && $this->editable($user, $insuranceProvider);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:InsuranceProvider');
    }
}
