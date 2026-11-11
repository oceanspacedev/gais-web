<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Filament\Widgets\ActiveAssetServicesTable;
use App\Filament\Widgets\AssetConditionChart;
use App\Filament\Widgets\AssetIncidentsTable;
use App\Filament\Widgets\AssetPurchaseTrendChart;
use App\Filament\Widgets\AssetsByBusinessEntityChart;
use App\Filament\Widgets\AssetTransferTrendChart;
use App\Filament\Widgets\AssetValueByCategoryChart;
use App\Filament\Widgets\AttentionStats;
use App\Filament\Widgets\ContractOverviewStats;
use App\Filament\Widgets\ExpiringInsurancesTable;
use App\Filament\Widgets\ExpiringRentsTable;
use App\Filament\Widgets\InsuranceCostChart;
use App\Filament\Widgets\ItemRequestCostByDivisionChart;
use App\Filament\Widgets\ItemRequestCostChart;
use App\Filament\Widgets\ItemRequestItemsChart;
use App\Filament\Widgets\ItemRequestStageChart;
use App\Filament\Widgets\ItemRequestTrendChart;
use App\Filament\Widgets\MasterDataOverview;
use App\Filament\Widgets\ObChecksheetTrendChart;
use App\Filament\Widgets\OpenProblemReportsTable;
use App\Filament\Widgets\OpenVendorTasksTable;
use App\Filament\Widgets\OperationsOverviewStats;
use App\Filament\Widgets\PendingItemRequestsTable;
use App\Filament\Widgets\ProblemReportCategoryChart;
use App\Filament\Widgets\ProblemReportCountChart;
use App\Filament\Widgets\ProblemReportOverviewStats;
use App\Filament\Widgets\ProblemReportTrendChart;
use App\Filament\Widgets\RentExpiryChart;
use App\Filament\Widgets\SumoOverviewStats;
use App\Filament\Widgets\VehiclesOutTable;
use App\Filament\Widgets\VehicleUsageChart;
use App\Filament\Widgets\VendorTaskCostChart;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dasbor manajemen: setiap widget punya izin widget_* dan bisa dirender,
 * halaman dasbor menyusun seksi per modul sesuai izin pembaca.
 */
class SumoDashboardWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return list<array{0: class-string}>
     */
    public static function widgets(): array
    {
        return array_map(fn (string $widget): array => [$widget], [
            AttentionStats::class,
            CustomAssetWidget::class,
            AssetConditionChart::class,
            AssetValueByCategoryChart::class,
            AssetPurchaseTrendChart::class,
            AssetTransferTrendChart::class,
            AssetsByBusinessEntityChart::class,
            AssetIncidentsTable::class,
            ActiveAssetServicesTable::class,
            SumoOverviewStats::class,
            ItemRequestTrendChart::class,
            ItemRequestStageChart::class,
            ItemRequestItemsChart::class,
            ItemRequestCostChart::class,
            ItemRequestCostByDivisionChart::class,
            PendingItemRequestsTable::class,
            ProblemReportOverviewStats::class,
            ProblemReportTrendChart::class,
            ProblemReportCountChart::class,
            ProblemReportCategoryChart::class,
            OpenProblemReportsTable::class,
            ContractOverviewStats::class,
            InsuranceCostChart::class,
            RentExpiryChart::class,
            ExpiringInsurancesTable::class,
            ExpiringRentsTable::class,
            OperationsOverviewStats::class,
            ObChecksheetTrendChart::class,
            VehicleUsageChart::class,
            VendorTaskCostChart::class,
            OpenVendorTasksTable::class,
            VehiclesOutTable::class,
            MasterDataOverview::class,
        ]);
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('widgets')]
    public function test_every_dashboard_widget_renders_for_a_super_admin(string $widget): void
    {
        $this->actingAsSuperAdmin();

        $this->assertTrue(Permission::query()->where('name', 'widget_'.class_basename($widget))->where('guard_name', 'web')->exists(), 'Izin widget_'.class_basename($widget).' belum dibuat migrasi.');
        $this->assertTrue($widget::canView(), "{$widget} should be visible to a super admin.");

        $component = Livewire::test($widget)->assertOk();

        match ($widget) {
            AttentionStats::class => $component->assertSee('Pengajuan Menunggu Executor')->assertSee('Kendaraan Belum Kembali'),
            CustomAssetWidget::class => $component->assertSee('Nilai Aset Aktif')->assertSee('Rusak / Hilang'),
            SumoOverviewStats::class => $component->assertSee('Pengajuan Tahun Ini')->assertSee('Siap Diproses Executor'),
            ProblemReportOverviewStats::class => $component->assertSee('Laporan Terbuka')->assertSee('Rata-rata Penyelesaian'),
            ContractOverviewStats::class => $component->assertSee('Polis Asuransi Aktif')->assertSee('Sewa Aktif'),
            OperationsOverviewStats::class => $component->assertSee('Kebersihan Hari Ini')->assertSee('Pekerjaan Vendor Berjalan'),
            MasterDataOverview::class => $component->assertSee('Organisasi')->assertSee('Master Aset'),
            default => $component,
        };
    }

    public function test_dashboard_page_shows_every_module_section_to_a_super_admin(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Perlu Tindakan')
            ->assertSee('Aset')
            ->assertSee('Pengajuan Barang')
            ->assertSee('Lapor Gangguan')
            ->assertSee('Asuransi & Sewa')
            ->assertSee('Operasional')
            ->assertSee('Organisasi & Data Master')
            ->assertDontSee('overlook');
    }

    public function test_dashboard_page_hides_sections_the_viewer_may_not_see(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $requester = User::factory()->create();
        $requester->assignRole(Role::findByName('requester', 'web'));
        $this->actingAs($requester->fresh());

        $this->assertTrue(SumoOverviewStats::canView());
        $this->assertFalse(CustomAssetWidget::canView());
        $this->assertFalse(ContractOverviewStats::canView());

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Pengajuan Barang')
            ->assertDontSee('Ringkasan Aset')
            ->assertDontSee('Asuransi & Sewa')
            ->assertDontSee('Organisasi & Data Master');
    }

    public function test_widgets_stay_hidden_without_their_permission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create());

        $this->assertFalse(AttentionStats::canView());
        $this->assertFalse(SumoOverviewStats::canView());
        $this->assertFalse(InsuranceCostChart::canView());
        $this->assertFalse(MasterDataOverview::canView());

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Perlu Tindakan')
            ->assertDontSee('Ringkasan Aset');
    }

    private function actingAsSuperAdmin(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findByName(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
        $this->actingAs($admin->fresh());

        return $admin;
    }
}
