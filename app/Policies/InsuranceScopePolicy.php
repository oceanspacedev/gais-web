<?php

namespace App\Policies;

use App\Models\InsuranceScope;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InsuranceScopePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance::scope');
    }

    public function view(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('view_insurance::scope');
    }

    public function create(User $user): bool
    {
        return $user->can('create_insurance::scope');
    }

    public function update(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('update_insurance::scope');
    }

    public function delete(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('delete_insurance::scope');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_insurance::scope');
    }

    public function forceDelete(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('force_delete_insurance::scope');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_insurance::scope');
    }

    public function restore(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('restore_insurance::scope');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_insurance::scope');
    }

    public function replicate(User $user, InsuranceScope $insuranceScope): bool
    {
        return $user->can('replicate_insurance::scope');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_insurance::scope');
    }
}
