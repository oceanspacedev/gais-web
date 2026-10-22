<?php

namespace App\Policies;

use App\Models\RentUpdate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pembaharuan perjanjian sewa mengikuti izin sewanya: melihat = lihat sewa,
 * menambah/mengubah/menghapus = ubah sewa.
 */
class RentUpdatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_rent');
    }

    public function view(User $user, RentUpdate $update): bool
    {
        return $user->can('view_rent');
    }

    public function create(User $user): bool
    {
        return $user->can('update_rent');
    }

    public function update(User $user, RentUpdate $update): bool
    {
        return $user->can('update_rent');
    }

    public function delete(User $user, RentUpdate $update): bool
    {
        return $user->can('update_rent');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_rent');
    }
}
