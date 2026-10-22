<?php

namespace App\Policies;

use App\Models\InsuranceUpdate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pembaharuan polis mengikuti izin polisnya: melihat = lihat asuransi,
 * menambah/mengubah/menghapus = ubah asuransi.
 */
class InsuranceUpdatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_insurance');
    }

    public function view(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('view_insurance');
    }

    public function create(User $user): bool
    {
        return $user->can('update_insurance');
    }

    public function update(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('update_insurance');
    }

    public function delete(User $user, InsuranceUpdate $update): bool
    {
        return $user->can('update_insurance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_insurance');
    }
}
