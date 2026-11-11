<?php

namespace App\Access\ProblemReports;

use App\Access\Grant;
use App\Models\ProblemReportScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemegang "Lihat Data Semua User" melihat semua laporan, kecuali divisinya
 * punya cakupan jenis gangguan (problem_report_scopes, diatur di menu Divisi):
 * hanya jenis yang disebut, atau semua jenis kecuali yang dikecualikan (sumo:
 * HCM hanya UMUM, MIS-IT selain UMUM). Laporan tanpa jenis ikut tersembunyi
 * bagi divisi yang dibatasi.
 */
final class DivisionCategoryGrant implements Grant
{
    public function __construct(private readonly string $permission = 'view_all_problem::report') {}

    public function appliesTo(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function grantsEverything(User $user): bool
    {
        return $this->scopes($user)->isEmpty();
    }

    public function apply(Builder $query, User $user): void
    {
        [$included, $excluded] = $this->categoryIds($user);
        $column = $query->qualifyColumn('problem_report_category_id');

        if ($included !== []) {
            $query->whereIn($column, $included);
        }

        if ($excluded !== []) {
            $query->whereNotNull($column)->whereNotIn($column, $excluded);
        }
    }

    public function allows(User $user, Model $record): ?bool
    {
        [$included, $excluded] = $this->categoryIds($user);
        $categoryId = $record->getAttribute('problem_report_category_id');

        if ($categoryId === null) {
            return false;
        }

        if ($included !== [] && ! in_array((int) $categoryId, $included, true)) {
            return false;
        }

        return ! in_array((int) $categoryId, $excluded, true);
    }

    /**
     * @return array{0: list<int>, 1: list<int>} [jenis yang boleh, jenis yang dikecualikan]
     */
    private function categoryIds(User $user): array
    {
        $scopes = $this->scopes($user);
        $ids = fn (bool $excluded): array => $scopes
            ->where('excluded', $excluded)
            ->map(fn (ProblemReportScope $scope): int => (int) $scope->problem_report_category_id)
            ->values()
            ->all();

        return [$ids(false), $ids(true)];
    }

    /**
     * @return Collection<int, ProblemReportScope>
     */
    private function scopes(User $user): Collection
    {
        if ($user->division_id === null) {
            return new Collection;
        }

        return ProblemReportScope::forDivision((int) $user->division_id);
    }
}
