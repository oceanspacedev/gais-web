<?php

namespace App\Support;

use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pelapor melihat laporannya sendiri. Pemegang "Lihat Data Semua User" melihat
 * semua laporan, kecuali divisinya dibatasi ke jenis gangguan tertentu di
 * config/problem-report.php (sumo: HCM hanya gangguan UMUM).
 */
final class ProblemReportVisibility
{
    public static function apply(Builder $query, User $user): Builder
    {
        if (! $user->can('view_all_problem::report')) {
            return $query->where('problem_reports.user_id', $user->id);
        }

        $categories = self::restrictedCategoriesFor($user);

        if ($categories === null) {
            return $query;
        }

        return $query->whereIn(
            'problem_reports.problem_report_category_id',
            ProblemReportCategory::withTrashed()->select('problem_report_categories.id')->whereIn('name', $categories),
        );
    }

    /**
     * Dipanggil policy untuk setiap record dan setiap aksi di tabel, jadi aturan
     * apply() dievaluasi di PHP bila bisa; selain itu jatuh ke query.
     */
    public static function allows(User $user, ProblemReport $report): bool
    {
        if (! $report->isAccessibleBy($user)) {
            return false;
        }

        // Query visibleTo memakai scope SoftDeletes, jadi laporan terhapus tidak pernah lolos.
        if ($report->trashed()) {
            return false;
        }

        if (! $user->can('view_all_problem::report')) {
            return (int) $report->user_id === $user->getKey();
        }

        $categories = self::restrictedCategoriesFor($user);

        if ($categories === null) {
            return true;
        }

        if ($report->relationLoaded('category')) {
            // whereIn('name') di MySQL tidak peka huruf besar/kecil (collation *_ci).
            $name = $report->category?->name;

            return $name !== null && in_array(mb_strtolower($name), array_map('mb_strtolower', $categories), true);
        }

        return ProblemReport::query()->whereKey($report->getKey())->visibleTo($user)->exists();
    }

    /**
     * @return list<string>|null
     */
    private static function restrictedCategoriesFor(User $user): ?array
    {
        $divisionName = $user->division?->name;

        if ($divisionName === null) {
            return null;
        }

        $map = config('problem-report.division_categories', []);

        foreach ($map as $division => $categories) {
            if (strcasecmp((string) $division, $divisionName) === 0) {
                return array_values((array) $categories);
            }
        }

        return null;
    }
}
