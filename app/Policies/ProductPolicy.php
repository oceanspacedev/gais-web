<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Product');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('View:Product') && $this->visible($user, $product);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Product');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('Update:Product') && $this->editable($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('Delete:Product') && $this->editable($user, $product);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Product');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('ForceDelete:Product') && $this->visible($user, $product);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Product');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('Restore:Product') && $this->visible($user, $product);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Product');
    }

    public function replicate(User $user, Product $product): bool
    {
        return $user->can('Replicate:Product') && $this->editable($user, $product);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Product');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:Product');
    }

    /**
     * Impor dari Excel.
     */
    public function import(User $user): bool
    {
        return $user->can('Import:Product');
    }
}
