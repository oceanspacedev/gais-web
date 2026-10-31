<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Resources\ProblemReportResource;
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
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dasbor manajemen: antrean lintas modul di atas, lalu satu seksi per modul
 * (Aset, Pengajuan Barang, Lapor Gangguan, Asuransi & Sewa, Operasional,
 * Organisasi & Data Master). Tiap widget dijaga izin `widget_*` Shield; seksi
 * yang tidak punya widget yang boleh dilihat tidak dirender, jadi pemohon
 * hanya melihat ringkasan pengajuannya sendiri sedangkan manajemen melihat
 * semuanya. Seksi bisa dilipat dan pilihannya diingat per browser.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dasbor';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ringkasan seluruh modul GAIS sesuai hak akses Anda, per '.now()->locale('id')->isoFormat('D MMMM YYYY HH:mm').'.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(array_values(array_filter([
            ...$this->getWidgetsSchemaComponents([AttentionStats::class]),
            $this->section(
                'aset',
                'Aset',
                'Kondisi, nilai perolehan, mutasi berita acara, insiden, dan servis aset.',
                Heroicon::OutlinedCube,
                AssetResource::class,
                [
                    CustomAssetWidget::class,
                    AssetConditionChart::class,
                    AssetValueByCategoryChart::class,
                    AssetPurchaseTrendChart::class,
                    AssetTransferTrendChart::class,
                    AssetsByBusinessEntityChart::class,
                    AssetIncidentsTable::class,
                    ActiveAssetServicesTable::class,
                ],
            ),
            $this->section(
                'pengajuan',
                'Pengajuan Barang',
                'Antrean per tahap persetujuan, tren bulanan, dan biaya per pemohon dan divisi.',
                Heroicon::OutlinedShoppingCart,
                ItemRequestResource::class,
                [
                    SumoOverviewStats::class,
                    ItemRequestTrendChart::class,
                    ItemRequestStageChart::class,
                    ItemRequestItemsChart::class,
                    ItemRequestCostChart::class,
                    ItemRequestCostByDivisionChart::class,
                    PendingItemRequestsTable::class,
                ],
            ),
            $this->section(
                'gangguan',
                'Lapor Gangguan',
                'Laporan terbuka, kecepatan penanganan, jenis gangguan, dan pelapor terbanyak.',
                Heroicon::OutlinedExclamationTriangle,
                ProblemReportResource::class,
                [
                    ProblemReportOverviewStats::class,
                    ProblemReportTrendChart::class,
                    ProblemReportCategoryChart::class,
                    ProblemReportCountChart::class,
                    OpenProblemReportsTable::class,
                ],
            ),
            $this->section(
                'kontrak',
                'Asuransi & Sewa',
                'Polis dan kontrak berjalan, premi dan nilai sewa, serta yang mendekati jatuh tempo.',
                Heroicon::OutlinedShieldCheck,
                InsuranceResource::class,
                [
                    ContractOverviewStats::class,
                    InsuranceCostChart::class,
                    RentExpiryChart::class,
                    ExpiringInsurancesTable::class,
                    ExpiringRentsTable::class,
                ],
            ),
            $this->section(
                'operasional',
                'Operasional',
                'Kebersihan harian (OB), pemakaian kendaraan, dan pekerjaan vendor.',
                Heroicon::OutlinedClipboardDocumentCheck,
                ObChecksheetResource::class,
                [
                    OperationsOverviewStats::class,
                    ObChecksheetTrendChart::class,
                    VehicleUsageChart::class,
                    VendorTaskCostChart::class,
                    OpenVendorTasksTable::class,
                    VehiclesOutTable::class,
                ],
            ),
            $this->section(
                'master',
                'Organisasi & Data Master',
                'Jumlah record master per menu.',
                Heroicon::OutlinedBuildingOffice2,
                null,
                [MasterDataOverview::class],
                collapsed: true,
            ),
        ])));
    }

    /**
     * Satu seksi modul; null bila tidak ada widget yang boleh dilihat pengguna.
     *
     * @param  class-string<resource>|null  $resource  menu utama modul, untuk tautan "Buka ..." di kepala seksi
     * @param  list<class-string<Widget>>  $widgets
     */
    protected function section(string $id, string $heading, string $description, Heroicon $icon, ?string $resource, array $widgets, bool $collapsed = false): ?Section
    {
        $components = $this->getWidgetsSchemaComponents($widgets);

        if ($components === []) {
            return null;
        }

        $section = Section::make($heading)
            ->id("dashboard-{$id}")
            ->description($description)
            ->icon($icon)
            ->collapsible()
            ->collapsed($collapsed)
            ->persistCollapsed()
            ->columns(['default' => 1, 'lg' => 12])
            ->schema($components);

        if ($resource !== null && $resource::canViewAny()) {
            $section->afterHeader([
                Action::make("open-{$id}")
                    ->label('Buka '.$resource::getPluralModelLabel())
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->link()
                    ->url($resource::getUrl()),
            ]);
        }

        return $section;
    }
}
