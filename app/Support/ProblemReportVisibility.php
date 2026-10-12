<?php

namespace App\Support;

use App\Models\ProblemReport;
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

        return $query->whereHas('category', fn (Builder $category) => $category->whereIn('name', $categories));
    }

    public static function allows(User $user, ProblemReport $report): bool
    {
        if (! $report->isAccessibleBy($user)) {
            return false;
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
