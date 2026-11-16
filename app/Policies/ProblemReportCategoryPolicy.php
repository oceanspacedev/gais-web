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
        return $user->can('ViewAny:ProblemReportCategory');
    }

    public function view(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('View:ProblemReportCategory') && $this->visible($user, $problemReportCategory);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ProblemReportCategory');
    }

    public function update(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('Update:ProblemReportCategory') && $this->editable($user, $problemReportCategory);
    }

    public function delete(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('Delete:ProblemReportCategory') && $this->editable($user, $problemReportCategory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ProblemReportCategory');
    }

    public function forceDelete(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('ForceDelete:ProblemReportCategory') && $this->visible($user, $problemReportCategory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ProblemReportCategory');
    }

    public function restore(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('Restore:ProblemReportCategory') && $this->visible($user, $problemReportCategory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ProblemReportCategory');
    }

    public function replicate(User $user, ProblemReportCategory $problemReportCategory): bool
    {
        return $user->can('Replicate:ProblemReportCategory') && $this->editable($user, $problemReportCategory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ProblemReportCategory');
    }
}
