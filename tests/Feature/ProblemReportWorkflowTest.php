<?php

namespace Tests\Feature;

use App\Enums\ProblemReportStatus;
use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ProblemReportResource\Pages\ListProblemReports;
use App\Filament\Resources\ProblemReportResource\Pages\ViewProblemReport;
use App\Models\Division;
use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\ProblemReportScope;
use App\Models\User;
use App\Services\ProblemReportWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProblemReportWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private ProblemReportWorkflow $workflow;

    private ProblemReportCategory $umum;

    private ProblemReportCategory $komputer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->workflow = app(ProblemReportWorkflow::class);
        $this->umum = ProblemReportCategory::create(['name' => 'UMUM']);
        $this->komputer = ProblemReportCategory::create(['name' => 'KOMPUTER-test']);
    }

    public function test_reporter_may_only_have_one_open_report_and_confirms_after_closing(): void
    {
        $reporter = User::factory()->create();
        $executor = User::factory()->create();

        $report = $this->report($reporter, $this->komputer);
        $this->assertSame('PR'.$report->id, $report->code);

        try {
            $this->report($reporter, $this->komputer);
            $this->fail('Laporan kedua seharusnya ditolak.');
        } catch (ItemRequestException $exception) {
            $this->assertStringContainsString('menunggu', $exception->getMessage());
        }

        try {
            $this->workflow->confirm($report, $reporter);
            $this->fail('Konfirmasi sebelum ditutup seharusnya ditolak.');
        } catch (ItemRequestException) {
            $this->assertTrue(true);
        }

        $this->workflow->process($report, $executor, ProblemReportStatus::Closed, now(), 'Problem_Report_File/after.jpg', 'diganti kabel');
        $report->refresh();
        $this->assertSame(ProblemReportStatus::Closed, $report->status);
        $this->assertSame($executor->id, $report->closed_by_user_id);
        $this->assertFalse($report->isConfirmed());

        $this->workflow->confirm($report, $reporter, 'mantap');
        $this->assertTrue($report->refresh()->isConfirmed());
        $this->assertInstanceOf(ProblemReport::class, $this->report($reporter, $this->komputer));
    }

    public function test_cancelling_confirms_immediately_and_closed_reports_auto_confirm_after_a_day(): void
    {
        $executor = User::factory()->create();
        $cancelled = $this->report(User::factory()->create(), $this->komputer);
        $this->workflow->process($cancelled, $executor, ProblemReportStatus::Cancelled, null, null, 'duplikat');
        $this->assertTrue($cancelled->refresh()->isConfirmed());

        $old = $this->report(User::factory()->create(), $this->komputer);
        $this->workflow->process($old, $executor, ProblemReportStatus::Closed);
        $old->forceFill(['closed_at' => now()->subDays(2)])->save();

        $fresh = $this->report(User::factory()->create(), $this->komputer);
        $this->workflow->process($fresh, $executor, ProblemReportStatus::Closed);

        $this->artisan('problem-reports:auto-confirm')->assertSuccessful();

        $this->assertTrue($old->refresh()->isConfirmed());
        $this->assertFalse($fresh->refresh()->isConfirmed());
    }

    public function test_hcm_executor_only_sees_general_reports_and_reporter_sees_own(): void
    {
        $hcm = Division::firstOrCreate(['name' => 'HCM']);
        // Sumo: executor HCM hanya menangani gangguan UMUM (cakupan di menu Divisi).
        ProblemReportScope::firstOrCreate(['division_id' => $hcm->id, 'problem_report_category_id' => $this->umum->id]);
        $executor = User::factory()->create(['division_id' => $hcm->id]);
        $executor->assignRole(Role::findByName('executor', 'web'));
        $reporter = User::factory()->create();
        $reporter->assignRole(Role::findByName('requester', 'web'));

        $general = $this->report($reporter, $this->umum);
        $it = $this->report(User::factory()->create(), $this->komputer);

        $this->actingAs($executor);
        Livewire::test(ListProblemReports::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$general])
            ->assertCanNotSeeTableRecords([$it]);
        Livewire::test(ViewProblemReport::class, ['record' => $general->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('process')
            ->assertActionHidden('confirm');

        $this->actingAs($reporter);
        Livewire::test(ListProblemReports::class)
            ->assertCanSeeTableRecords([$general])
            ->assertCanNotSeeTableRecords([$it]);
    }

    private function report(User $reporter, ProblemReportCategory $category): ProblemReport
    {
        return $this->workflow->create($reporter, [
            'problem_report_category_id' => $category->id,
            'description' => 'Rusak',
            'photo_before' => 'Problem_Report_File/before.jpg',
        ]);
    }
}
