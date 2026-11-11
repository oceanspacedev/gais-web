<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ProblemReportCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProblemReportCategoryPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_problem::report::category');
    }

    public function view(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('view_problem::report::category') && $this->visible($user, $problemReportCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('create_problem::report::category');
    }

    public function update(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('update_problem::report::category') && $this->editable($user, $problemReportCategory);
    }

    public function delete(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('delete_problem::report::category') && $this->editable($user, $problemReportCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_problem::report::category');
    }

    public function forceDelete(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('force_delete_problem::report::category') && $this->visible($user, $problemReportCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_problem::report::category');
    }

    public function restore(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('restore_problem::report::category') && $this->visible($user, $problemReportCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_problem::report::category');
    }

    public function replicate(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('replicate_problem::report::category') && $this->editable($user, $problemReportCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_problem::report::category');
    }
}
