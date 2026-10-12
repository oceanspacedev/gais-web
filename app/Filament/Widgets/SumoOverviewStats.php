<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Resources\RentResource;
use App\Models\ItemRequestSetting;
use App\Models\ProblemReport;
use App\Models\Product;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Beranda sumo: ringkasan pengajuan, gangguan, barang, asuransi, sewa, dan
 * periode cutoff pengajuan yang sedang berjalan. Angka mengikuti hak akses pembaca.
 */
class SumoOverviewStats extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Pengajuan, Gangguan, Asuransi & Sewa';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $requests = ItemRequestResource::getEloquentQuery();
        $totalRequests = (clone $requests)->count();
        $acceptedRequests = (clone $requests)->where('client_status', ItemRequestClientStatus::Accepted->value)->count();

        $reports = ProblemReportResource::getEloquentQuery();
        $totalReports = (clone $reports)->count();
        $confirmedReports = (clone $reports)->where('client_status', ProblemReport::CLIENT_CONFIRMED)->count();

        $period = ItemRequestSetting::current();
        $periodLabel = $period?->closed_date
            ? sprintf('%s: %s s.d. %s', $period->request_detail ?? 'Periode pengajuan', $period->open_date?->format('d/m/Y') ?? '-', $period->closed_date->format('d/m/Y'))
            : 'Periode pengajuan belum diatur';

        return [
            Stat::make('Pengajuan Barang', number_format($totalRequests))
                ->description("{$acceptedRequests} diterima · ".($totalRequests - $acceptedRequests).' belum selesai')
                ->color('primary'),
            Stat::make('Laporan Gangguan', number_format($totalReports))
                ->description("{$confirmedReports} selesai · ".($totalReports - $confirmedReports).' menunggu')
                ->color('warning'),
            Stat::make('Barang', number_format(Product::query()->count()))
                ->description($periodLabel)
                ->color('gray'),
            Stat::make('Asuransi & Sewa Aktif', number_format(InsuranceResource::getEloquentQuery()->activeContracts()->count()).' / '.number_format(RentResource::getEloquentQuery()->activeContracts()->count()))
                ->description('polis / kontrak yang masih berjalan')
                ->color('success'),
        ];
    }
}
