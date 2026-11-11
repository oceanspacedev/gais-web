<?php

namespace Tests\Feature;

use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BaselineAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_without_any_role_can_open_the_panel_and_report_a_problem(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $employee = User::factory()->create();
        $this->assertFalse($employee->roles()->exists());

        $this->assertTrue($employee->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($employee->can('viewAny', ProblemReport::class));
        $this->assertTrue($employee->can('create', ProblemReport::class));

        // Akses dasar tidak melebar ke pemrosesan, ekspor, atau laporan orang lain.
        $this->assertFalse($employee->can('process', ProblemReport::class));
        $this->assertFalse($employee->can('export', ProblemReport::class));
        $this->assertFalse($employee->can('viewAll', ProblemReport::class));
        $this->assertFalse($employee->can('deleteAny', ProblemReport::class));

        $this->actingAs($employee);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(ProblemReportResource::getUrl('index'))->assertOk();
        $this->get(ProblemReportResource::getUrl('create'))->assertOk();
    }

    public function test_employee_only_sees_their_own_reports(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $employee = User::factory()->create();
        $own = ProblemReport::create(['user_id' => $employee->id, 'reported_at' => now()]);
        $foreign = ProblemReport::create(['user_id' => User::factory()->create()->id, 'reported_at' => now()]);

        $this->assertTrue($employee->can('view', $own));
        $this->assertFalse($employee->can('view', $foreign));
    }
}
