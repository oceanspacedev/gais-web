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
        return $user->can('view_any_product::category');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('view_product::category') && $this->visible($user, $productCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('create_product::category');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('update_product::category') && $this->editable($user, $productCategory);
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('delete_product::category') && $this->editable($user, $productCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_product::category');
    }

    public function forceDelete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('force_delete_product::category') && $this->visible($user, $productCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_product::category');
    }

    public function restore(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('restore_product::category') && $this->visible($user, $productCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_product::category');
    }

    public function replicate(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('replicate_product::category') && $this->editable($user, $productCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_product::category');
    }
}
