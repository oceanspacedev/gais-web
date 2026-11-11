<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Division;
use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\ProblemReportScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AssertsVisibilityContract;
use Tests\TestCase;

class ProblemReportVisibilityTest extends TestCase
{
    use AssertsVisibilityContract, DatabaseTransactions;

    public function test_policy_check_agrees_with_the_visibility_query(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $general = ProblemReportCategory::create(['name' => 'Umum']);
        $it = ProblemReportCategory::create(['name' => 'IT-test']);
        $retired = ProblemReportCategory::create(['name' => 'UMUM']);
        $hcm = Division::create(['name' => 'HCM-test']);
        $itDivision = Division::create(['name' => 'IT-test']);
        $unrestricted = Division::create(['name' => 'FREE-test']);

        // Sumo: HCM hanya UMUM (dua jenis bernama Umum, termasuk yang nanti dihapus),
        // executor lain selain UMUM.
        foreach ([$general, $retired] as $category) {
            ProblemReportScope::create(['division_id' => $hcm->id, 'problem_report_category_id' => $category->id]);
            ProblemReportScope::create(['division_id' => $itDivision->id, 'problem_report_category_id' => $category->id, 'excluded' => true]);
        }

        $reporter = User::factory()->create();
        $viewAll = Permission::findOrCreate('view_all_problem::report', 'web');

        $viewers = [
            'reporter' => $reporter,
            'stranger' => User::factory()->create(),
            'view all' => User::factory()->create()->givePermissionTo($viewAll),
            'view all, unrestricted division' => User::factory()->create(['division_id' => $unrestricted->id])->givePermissionTo($viewAll),
            'view all, restricted division' => User::factory()->create(['division_id' => $hcm->id])->givePermissionTo($viewAll),
            'view all, excluded category' => User::factory()->create(['division_id' => $itDivision->id])->givePermissionTo($viewAll),
            'restricted division without permission' => User::factory()->create(['division_id' => $hcm->id]),
        ];

        $otherEntity = BusinessEntity::create(['name' => '__prv_other__']);
        $restrictedReporter = User::factory()->create(['business_entity_id' => BusinessEntity::create(['name' => '__prv_home__'])->id]);
        $viewers['restricted reporter'] = $restrictedReporter;

        $reports = [
            $this->report($reporter, $general),
            $this->report($reporter, $it),
            $this->report($reporter, null),
            $this->report(User::factory()->create(), $general),
            $this->report(User::factory()->create(), $retired),
        ];
        $outside = $this->report($restrictedReporter, $general);
        $outside->forceFill(['business_entity_id' => $otherEntity->id])->save();
        $reports[] = $outside;
        $trashed = $this->report($reporter, $general);
        $trashed->delete();
        $retired->delete();

        $ids = [...array_map(fn (ProblemReport $report): int => $report->id, $reports), $trashed->id];

        // Laporan tanpa jenis ikut tersembunyi bagi divisi yang dibatasi.
        $this->assertSame([$reports[0]->id, $reports[3]->id, $reports[4]->id], $this->visibleIds($viewers['view all, restricted division'], $ids));
        $this->assertSame([$reports[1]->id], $this->visibleIds($viewers['view all, excluded category'], $ids));
        $this->assertSame(array_map(fn (ProblemReport $report): int => $report->id, array_slice($reports, 0, 5)), $this->visibleIds($viewers['view all, unrestricted division'], $ids), 'Lihat semua tetap dibatasi badan usaha.');
        $this->assertSame([], $this->visibleIds($restrictedReporter, $ids), 'Laporan sendiri di badan usaha lain tetap tersembunyi.');
        $this->assertSame([$reports[0]->id, $reports[1]->id, $reports[2]->id], $this->visibleIds($viewers['reporter'], $ids));

        $this->assertQueryAndPolicyAgree(ProblemReport::class, $viewers, $ids, [
            'eager' => ['category'],
            'lazy' => [],
        ]);
    }

    private function report(User $user, ?ProblemReportCategory $category): ProblemReport
    {
        return ProblemReport::create([
            'user_id' => $user->id,
            'problem_report_category_id' => $category?->id,
            'reported_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function visibleIds(User $viewer, array $ids): array
    {
        return ProblemReport::query()->whereKey($ids)->visibleTo($viewer)->orderBy('id')->pluck('id')->all();
    }
}
