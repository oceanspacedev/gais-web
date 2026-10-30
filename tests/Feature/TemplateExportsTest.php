<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Exports\InsuranceExport;
use App\Exports\ProblemReportExport;
use App\Exports\ProductExport;
use App\Exports\RentExport;
use App\Exports\UserExport;
use App\Filament\Resources\InsuranceResource\Pages\ListInsurances;
use App\Filament\Resources\ProblemReportResource\Pages\ListProblemReports;
use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\RentResource\Pages\ListRents;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Imports\InsuranceImport;
use App\Imports\ProductImport;
use App\Imports\RentImport;
use App\Models\Division;
use App\Models\Insurance;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rent;
use App\Models\UnitType;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ekspor penuh ala sumo (/product/export, /insurance/export, /rent/export,
 * /user/export, /problemReport/export): kolom mengikuti template impor supaya
 * hasilnya bisa diimpor kembali, dan tombolnya mengikuti izin export_*.
 */
class TemplateExportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->exporter = User::factory()->create(['access_all_business_entities' => true]);
        $this->exporter->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            [
                'view_any_product', 'view_product', 'export_product',
                'view_any_insurance', 'view_insurance', 'export_insurance',
                'view_any_rent', 'view_rent', 'export_rent',
                'view_any_user', 'view_user', 'export_user',
                'view_any_problem::report', 'view_problem::report', 'export_problem::report', 'view_all_problem::report',
            ],
        ));

        $this->actingAs($this->exporter);
    }

    public function test_export_headings_match_the_import_templates(): void
    {
        $this->assertSame(array_keys(ProductImport::sampleRow()), (new ProductExport)->headings());
        $this->assertSame(array_keys(InsuranceImport::sampleRow()), (new InsuranceExport)->headings());
        $this->assertSame(array_keys(RentImport::sampleRow()), (new RentExport)->headings());
        $this->assertSame(['username', 'fullname', 'badan_usaha', 'divisi', 'role', 'approval'], (new UserExport)->headings());
        $this->assertSame(
            ['kode', 'pelapor', 'tgl pelaporan', 'kategori', 'detail_pelaporan', 'status', 'penjadwalan', 'diproses oleh', 'diproses pada', 'hasil pengerjaan', 'status akhir'],
            (new ProblemReportExport(today(), today()))->headings(),
        );
    }

    public function test_rows_follow_the_sumo_columns(): void
    {
        $category = ProductCategory::create(['name' => 'ATK-eks']);
        $unit = UnitType::create(['name' => 'PCS-eks']);
        $product = Product::create(['name' => 'Pulpen Ekspor', 'product_category_id' => $category->id, 'unit_type_id' => $unit->id, 'price' => 2500, 'description' => 'hitam', 'stock' => 10]);
        $this->assertSame(['Pulpen Ekspor', 'ATK-eks', 'PCS-eks', 2500.0, 'hitam', 10.0], (new ProductExport)->map($product->fresh(['category', 'unitType'])));

        $provider = InsuranceProvider::create(['name' => 'Provider Ekspor Penuh']);
        $insuranceCategory = InsuranceCategory::create(['name' => 'GUDANG-eks']);
        $insurance = Insurance::create([
            'policy_number' => 'POL-EKSPOR-1', 'insured_name' => 'Gudang Ekspor', 'warehouse_code' => 'GD-1', 'stock_provider_id' => $provider->id,
            'insurance_category_id' => $insuranceCategory->id, 'stock_worth' => 1000, 'stock_premium' => 10,
            'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'status' => ContractStatus::Running->value, 'notes' => 'catatan',
        ]);
        $row = (new InsuranceExport)->map($insurance->fresh(['stockProvider', 'buildingProvider', 'category', 'scope']));
        $this->assertSame($insurance->id, $row[0]);
        $this->assertSame(['POL-EKSPOR-1', null, 'Gudang Ekspor', 'GD-1'], array_slice($row, 1, 4));
        $this->assertSame('Provider Ekspor Penuh', $row[7]);
        $this->assertSame(['GUDANG-eks', '2026-01-01', '2026-12-31', null, 'BERJALAN', 'catatan'], array_slice($row, 14));

        $rent = Rent::create([
            'rent_code' => 'RENT-EKSPOR-1', 'rented_address' => 'Jl. Ekspor', 'rented_detail' => 'Ruko Ekspor', 'first_party' => 'PT A', 'second_party' => 'Bpk B',
            'rent_per_year' => 12000000, 'cvcs_fund' => 5000000, 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31',
            'deduction_evidence' => 'ADA', 'document' => 'TIDAK ADA', 'month_before_reminder' => '2',
        ]);
        $this->assertSame(
            ['RENT-EKSPOR-1', 'Jl. Ekspor', 'Ruko Ekspor', 'PT A', 'Bpk B', 12000000, 5000000, null, '2026-01-01', '2026-12-31', 'ADA', 'TIDAK ADA', 'BERJALAN', '2', null],
            (new RentExport)->map($rent),
        );

        $division = Division::create(['name' => 'DIV-eks']);
        $approver = User::factory()->create(['name' => 'Atasan Ekspor']);
        $user = User::factory()->create(['name' => 'Staf Ekspor', 'username' => 'staf-eks', 'division_id' => $division->id, 'approver_user_id' => $approver->id]);
        $user->assignRole(Role::findOrCreate('role-eks', 'web'));
        $this->assertSame(['staf-eks', 'Staf Ekspor', null, 'DIV-eks', 'role-eks', 'Atasan Ekspor'], (new UserExport)->map($user->fresh(['businessEntity', 'division', 'approver', 'roles'])));
    }

    public function test_problem_report_export_respects_the_date_range_and_sumo_statuses(): void
    {
        $category = ProblemReportCategory::create(['name' => 'UMUM-eks']);
        $reporter = User::factory()->create(['name' => 'Pelapor Ekspor']);
        $inside = ProblemReport::create(['user_id' => $reporter->id, 'problem_report_category_id' => $category->id, 'reported_at' => '2026-03-10 09:00:00', 'description' => 'AC bocor', 'status' => 'CLOSED', 'client_status' => 1, 'closed_by_user_id' => $this->exporter->id, 'closed_at' => '2026-03-11 10:00:00', 'result_desc' => 'diganti', 'scheduled_at' => '2026-03-11']);
        ProblemReport::create(['user_id' => $reporter->id, 'problem_report_category_id' => $category->id, 'reported_at' => '2026-04-01 09:00:00', 'description' => 'di luar rentang']);

        $export = new ProblemReportExport(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));
        $rows = $export->collection();

        $this->assertSame([$inside->id], $rows->pluck('id')->all());
        $this->assertSame(
            [$inside->code, 'Pelapor Ekspor', '2026-03-10 09:00:00', 'UMUM-eks', 'AC bocor', 'CLOSED', '2026-03-11', $this->exporter->name, '2026-03-11 10:00:00', 'diganti', 'SELESAI'],
            $export->map($rows->first()),
        );
        $this->assertSame('laporan-gangguan-20260301-20260331.xlsx', $export->fileName());
    }

    public function test_export_buttons_download_files_and_hide_without_permission(): void
    {
        Livewire::test(ManageProducts::class)->assertActionVisible('exportAll')->callAction('exportAll')->assertFileDownloaded('barang.xlsx');
        Livewire::test(ListInsurances::class)->assertActionVisible('exportAll')->callAction('exportAll')->assertFileDownloaded('asuransi.xlsx');
        Livewire::test(ListRents::class)->assertActionVisible('exportAll')->callAction('exportAll')->assertFileDownloaded('sewa.xlsx');
        Livewire::test(ListUsers::class)->assertActionVisible('exportExcel')->callAction('exportExcel')->assertFileDownloaded('user.xlsx');
        Livewire::test(ListProblemReports::class)
            ->assertActionVisible('exportReports')
            ->callAction('exportReports', ['from' => '2026-03-01', 'until' => '2026-03-31'])
            ->assertFileDownloaded('laporan-gangguan-20260301-20260331.xlsx');

        $viewer = User::factory()->create(['access_all_business_entities' => true]);
        $viewer->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            ['view_any_product', 'view_any_insurance', 'view_any_rent', 'view_any_user', 'view_any_problem::report'],
        ));
        $this->actingAs($viewer);

        Livewire::test(ManageProducts::class)->assertActionHidden('exportAll');
        Livewire::test(ListInsurances::class)->assertActionHidden('exportAll');
        Livewire::test(ListRents::class)->assertActionHidden('exportAll');
        Livewire::test(ListUsers::class)->assertActionHidden('exportExcel');
        Livewire::test(ListProblemReports::class)->assertActionHidden('exportReports');
    }
}
