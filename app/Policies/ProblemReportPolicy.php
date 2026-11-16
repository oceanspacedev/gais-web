<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProblemReportPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ProblemReport');
    }

    public function view(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('View:ProblemReport') && $this->visible($user, $problemReport);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ProblemReport');
    }

    public function update(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('Update:ProblemReport') && $this->editable($user, $problemReport);
    }

    public function delete(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('Delete:ProblemReport') && $this->editable($user, $problemReport);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ProblemReport');
    }

    public function forceDelete(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('ForceDelete:ProblemReport') && $this->visible($user, $problemReport);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ProblemReport');
    }

    public function restore(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('Restore:ProblemReport') && $this->visible($user, $problemReport);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ProblemReport');
    }

    public function replicate(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('Replicate:ProblemReport') && $this->editable($user, $problemReport);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ProblemReport');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:ProblemReport');
    }

    /**
     * Data milik user lain; tanpa izin ini user hanya melihat data yang terkait dirinya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('ViewAll:ProblemReport');
    }

    /**
     * Tahap Executor: memproses, menyelesaikan, atau membatalkan.
     */
    public function process(User $user): bool
    {
        return $user->can('Process:ProblemReport');
    }
}
