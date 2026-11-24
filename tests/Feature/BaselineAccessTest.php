<?php

namespace Tests\Feature;

use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Filament\Resources\ProblemReportResource;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ProblemReport;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
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

    public function test_employee_placed_in_a_division_can_request_items_without_a_role(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $division = Division::create(['name' => 'TOKO-uji-akses-dasar']);
        $member = User::factory()->create(['division_id' => $division->id]);
        $unplaced = User::factory()->create();

        $this->assertTrue($member->can('viewAny', ItemRequest::class));
        $this->assertTrue($member->can('create', ItemRequest::class));
        $this->assertTrue($member->can('viewAny', Product::class));
        // Tanpa divisi: daftar terbuka (untuk memilih divisi), membuat belum boleh.
        $this->assertTrue($unplaced->can('viewAny', ItemRequest::class));
        $this->assertFalse($unplaced->can('create', ItemRequest::class));

        // Hanya izin pemohon: memproses, menyetujui, melihat semua, dan ekspor tetap butuh izin Shield.
        foreach (['Process:ItemRequest', 'ApproveManager:ItemRequest', 'ViewAll:ItemRequest', 'Export:ItemRequest', 'Update:ItemRequest', 'Create:Product'] as $permission) {
            $this->assertFalse($member->can($permission), $permission);
        }

        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(ItemRequestResource::getUrl('index'))->assertOk();
        $this->get(ItemRequestResource::getUrl('create'))->assertOk();
    }

    public function test_employee_without_division_chooses_it_once_then_can_request_items(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $division = Division::create(['name' => 'CS-uji-pilih-divisi']);
        $employee = User::factory()->create();

        $this->actingAs($employee);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(ItemRequestResource::getUrl('index'))->assertOk();
        $this->get(ItemRequestResource::getUrl('create'))->assertRedirect(ItemRequestResource::getUrl('index'));

        Livewire::test(ListItemRequests::class)
            ->assertSet('defaultAction', 'chooseDivision')
            ->assertActionVisible('chooseDivision')
            ->assertActionHidden('create')
            ->callAction('chooseDivision', ['division_id' => $division->id])
            ->assertHasNoActionErrors();

        $employee->refresh();
        $this->assertSame($division->id, $employee->division_id);
        $this->assertTrue($employee->can('create', ItemRequest::class));

        // Sekali saja: aksi hilang, dan pemanggilan ulang tidak mengubah divisi.
        $this->actingAs($employee);
        Livewire::test(ListItemRequests::class)
            ->assertSet('defaultAction', null)
            ->assertActionHidden('chooseDivision')
            ->assertActionVisible('create');

        $this->get(ItemRequestResource::getUrl('create'))->assertOk();
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
