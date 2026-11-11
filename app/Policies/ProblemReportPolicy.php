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
        return $user->can('view_any_problem::report');
    }

    public function view(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('view_problem::report') && $this->visible($user, $problemReport);
    }

    public function create(User $user): bool
    {
        return $user->can('create_problem::report');
    }

    public function update(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('update_problem::report') && $this->editable($user, $problemReport);
    }

    public function delete(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('delete_problem::report') && $this->editable($user, $problemReport);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_problem::report');
    }

    public function forceDelete(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('force_delete_problem::report') && $this->visible($user, $problemReport);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_problem::report');
    }

    public function restore(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('restore_problem::report') && $this->visible($user, $problemReport);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_problem::report');
    }

    public function replicate(User $user, ProblemReport $problemReport): bool
    {
        return $user->can('replicate_problem::report') && $this->editable($user, $problemReport);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_problem::report');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_problem::report');
    }

    /**
     * Data milik user lain; tanpa izin ini user hanya melihat data yang terkait dirinya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_problem::report');
    }

    /**
     * Tahap Executor: memproses, menyelesaikan, atau membatalkan.
     */
    public function process(User $user): bool
    {
        return $user->can('process_problem::report');
    }
}
