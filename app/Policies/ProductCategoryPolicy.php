<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductCategoryPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ProductCategory');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('View:ProductCategory') && $this->visible($user, $productCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ProductCategory');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('Update:ProductCategory') && $this->editable($user, $productCategory);
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('Delete:ProductCategory') && $this->editable($user, $productCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ProductCategory');
    }

    public function forceDelete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('ForceDelete:ProductCategory') && $this->visible($user, $productCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ProductCategory');
    }

    public function restore(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('Restore:ProductCategory') && $this->visible($user, $productCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ProductCategory');
    }

    public function replicate(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('Replicate:ProductCategory') && $this->editable($user, $productCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ProductCategory');
    }
}
