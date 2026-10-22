<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\User;
use App\Support\ProblemReportVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProblemReportVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_policy_check_agrees_with_the_visibility_query(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['problem-report.division_categories' => ['HCM-test' => ['UMUM']]]);

        // Nama beda huruf besar/kecil: whereIn('name') di MySQL tetap cocok.
        $general = ProblemReportCategory::create(['name' => 'Umum']);
        $it = ProblemReportCategory::create(['name' => 'IT-test']);
        $retired = ProblemReportCategory::create(['name' => 'UMUM']);
        $hcm = Division::create(['name' => 'HCM-test']);

        $reporter = User::factory()->create();
        $viewAll = Permission::findOrCreate('view_all_problem::report', 'web');

        $viewers = [
            'reporter' => $reporter,
            'stranger' => User::factory()->create(),
            'view all' => User::factory()->create()->givePermissionTo($viewAll),
            'view all, restricted division' => User::factory()->create(['division_id' => $hcm->id])->givePermissionTo($viewAll),
        ];

        $reports = [
            $this->report($reporter, $general),
            $this->report($reporter, $it),
            $this->report($reporter, null),
            $this->report(User::factory()->create(), $general),
            $this->report(User::factory()->create(), $retired),
        ];
        $trashed = $this->report($reporter, $general);
        $trashed->delete();
        $retired->delete();

        $ids = [...array_map(fn (ProblemReport $report): int => $report->id, $reports), $trashed->id];

        foreach ($viewers as $label => $viewer) {
            $visible = ProblemReport::query()->whereKey($ids)->visibleTo($viewer)->pluck('id')->all();

            $loadings = [
                'eager' => ProblemReport::withTrashed()->whereKey($ids)->with('category')->get(),
                'lazy' => ProblemReport::withTrashed()->whereKey($ids)->get(),
            ];

            foreach ($loadings as $loading => $records) {
                foreach ($records as $record) {
                    $this->assertSame(
                        in_array($record->id, $visible, true),
                        ProblemReportVisibility::allows($viewer, $record),
                        "{$label} / {$loading} / laporan #{$record->id}",
                    );
                }
            }
        }
    }

    private function report(User $user, ?ProblemReportCategory $category): ProblemReport
    {
        return ProblemReport::create([
            'user_id' => $user->id,
            'problem_report_category_id' => $category?->id,
            'reported_at' => now(),
        ]);
    }
}
