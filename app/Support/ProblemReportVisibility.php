<?php

namespace App\Support;

use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pelapor melihat laporannya sendiri. Pemegang "Lihat Data Semua User" melihat
 * semua laporan, kecuali divisinya dibatasi ke jenis gangguan tertentu di
 * config/problem-report.php: daftar jenis yang boleh, atau dengan awalan "!"
 * jenis yang dikecualikan (sumo: HCM hanya UMUM, executor lain selain UMUM).
 */
final class ProblemReportVisibility
{
    public static function apply(Builder $query, User $user): Builder
    {
        if (! $user->can('view_all_problem::report')) {
            return $query->where('problem_reports.user_id', $user->id);
        }

        $rule = self::restrictedCategoriesFor($user);

        if ($rule === null) {
            return $query;
        }

        [$include, $exclude] = $rule;
        $ids = fn (array $names): Builder => ProblemReportCategory::withTrashed()->select('problem_report_categories.id')->whereIn('name', $names);

        if ($include !== []) {
            $query->whereIn('problem_reports.problem_report_category_id', $ids($include));
        }

        if ($exclude !== []) {
            // Laporan tanpa jenis ikut tersembunyi, sama seperti whereIn di atas (sumo: pr_category_id != 7).
            $query->whereNotNull('problem_reports.problem_report_category_id')
                ->whereNotIn('problem_reports.problem_report_category_id', $ids($exclude));
        }

        return $query;
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

        $rule = self::restrictedCategoriesFor($user);

        if ($rule === null) {
            return true;
        }

        if ($report->relationLoaded('category')) {
            // whereIn('name') di MySQL tidak peka huruf besar/kecil (collation *_ci).
            $name = $report->category?->name;

            if ($name === null) {
                return false;
            }

            [$include, $exclude] = $rule;
            $lower = fn (array $names): array => array_map('mb_strtolower', $names);
            $name = mb_strtolower($name);

            if ($include !== [] && ! in_array($name, $lower($include), true)) {
                return false;
            }

            return $exclude === [] || ! in_array($name, $lower($exclude), true);
        }

        return ProblemReport::query()->whereKey($report->getKey())->visibleTo($user)->exists();
    }

    /**
     * @return array{0: list<string>, 1: list<string>}|null [jenis yang boleh, jenis yang dikecualikan]
     */
    private static function restrictedCategoriesFor(User $user): ?array
    {
        $divisionName = $user->division?->name;

        if ($divisionName === null) {
            return null;
        }

        $map = config('problem-report.division_categories', []);

        foreach ($map as $division => $categories) {
            if (strcasecmp((string) $division, $divisionName) !== 0) {
                continue;
            }

            $include = [];
            $exclude = [];

            foreach ((array) $categories as $category) {
                $category = trim((string) $category);

                if ($category === '') {
                    continue;
                }

                if (str_starts_with($category, '!')) {
                    $exclude[] = ltrim(substr($category, 1));
                } else {
                    $include[] = $category;
                }
            }

            return [$include, $exclude];
        }

        return null;
    }
}
