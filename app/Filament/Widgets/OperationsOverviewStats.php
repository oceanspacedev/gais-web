<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\VehicleChecksheetResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ObChecksheet;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Dasbor > Operasional: kebersihan (OB checksheet), pemakaian kendaraan, dan
 * pekerjaan vendor. Tiap kelompok hanya tampil bila menunya boleh dibuka.
 */
class OperationsOverviewStats extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Ringkasan Operasional';

    protected ?string $description = 'Kebersihan harian, pemakaian kendaraan, dan pekerjaan vendor.';

    protected ?string $pollingInterval = null;

    protected static function hasAnythingToShow(): bool
    {
        return ObChecksheetResource::canViewAny() || VehicleChecksheetResource::canViewAny() || TaskResource::canViewAny();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        return [
            ...(ObChecksheetResource::canViewAny() ? $this->obStats() : []),
            ...(VehicleChecksheetResource::canViewAny() ? $this->vehicleStats() : []),
            ...(TaskResource::canViewAny() ? $this->vendorTaskStats() : []),
        ];
    }

    /**
     * @return list<Stat>
     */
    protected function obStats(): array
    {
        $today = ObChecksheetResource::getEloquentQuery()->toBase()
            ->whereDate('scheduled_date', today())
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $scheduledToday = (int) $today->sum();
        $completedToday = (int) ($today[ObChecksheet::STATUS_COMPLETED] ?? 0);
        $unclaimed = (int) ObChecksheetResource::getEloquentQuery()->unclaimed()->whereDate('scheduled_date', '>=', today()->subDays(7))->count();

        $month = ObChecksheetResource::getEloquentQuery()->toBase()
            ->whereDate('scheduled_date', '>=', today()->subDays(29))
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed, AVG(CASE WHEN status = ? THEN duration_minutes END) AS minutes', [ObChecksheet::STATUS_COMPLETED, ObChecksheet::STATUS_COMPLETED])
            ->first();

        return [
            Stat::make('Kebersihan Hari Ini', Rupiah::number($completedToday).' / '.Rupiah::number($scheduledToday))
                ->description(Rupiah::number((int) ($today[ObChecksheet::STATUS_IN_PROGRESS] ?? 0)).' sedang dikerjakan · '.Rupiah::number((int) ($today[ObChecksheet::STATUS_PENDING] ?? 0)).' belum')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->color($scheduledToday === 0 ? 'gray' : ($completedToday >= $scheduledToday ? 'success' : 'warning'))
                ->url(ObChecksheetResource::getUrl(parameters: ['tableFilters' => ['scheduled_date' => ['from' => today()->toDateString(), 'until' => today()->toDateString()]]])),
            Stat::make('Tugas OB Belum Diambil', Rupiah::number($unclaimed))
                ->description('tugas kolam bersama 7 hari terakhir tanpa petugas')
                ->descriptionIcon(Heroicon::OutlinedHandRaised)
                ->color($unclaimed > 0 ? 'warning' : 'gray')
                ->url(ObChecksheetResource::getUrl(parameters: ['tableFilters' => ['unclaimed' => ['isActive' => true]]])),
            Stat::make('Penyelesaian OB 30 Hari', Rupiah::percent((int) ($month->completed ?? 0), (int) ($month->total ?? 0)))
                ->description(Rupiah::number((int) ($month->completed ?? 0)).' dari '.Rupiah::number((int) ($month->total ?? 0)).' tugas · rata-rata '.Rupiah::number((float) ($month->minutes ?? 0)).' menit')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
        ];
    }

    /**
     * @return list<Stat>
     */
    protected function vehicleStats(): array
    {
        $out = VehicleChecksheetResource::getEloquentQuery()->toBase()
            ->whereNull('return_time')
            ->selectRaw('COUNT(*) AS total, MIN(departure_time) AS oldest')
            ->first();

        $month = VehicleChecksheetResource::getEloquentQuery()->toBase()
            ->where('departure_time', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) AS trips, COALESCE(SUM(distance_traveled), 0) AS km, SUM(CASE WHEN departure_damage_report IS NOT NULL OR return_damage_report IS NOT NULL THEN 1 ELSE 0 END) AS damage')
            ->first();

        $oldest = filled($out->oldest ?? null) ? Carbon::parse($out->oldest) : null;

        return [
            Stat::make('Kendaraan Belum Kembali', Rupiah::number((int) ($out->total ?? 0)))
                ->description($oldest ? 'paling lama berangkat '.$oldest->diffForHumans() : 'semua checksheet sudah ditutup')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color((int) ($out->total ?? 0) > 0 ? 'danger' : 'gray')
                ->url(VehicleChecksheetResource::getUrl()),
            Stat::make('Perjalanan Bulan Ini', Rupiah::number((int) ($month->trips ?? 0)))
                ->description(Rupiah::number((float) ($month->km ?? 0)).' km · '.Rupiah::number((int) ($month->damage ?? 0)).' dengan laporan kerusakan')
                ->descriptionIcon(Heroicon::OutlinedMapPin)
                ->color('info')
                ->url(VehicleChecksheetResource::getUrl()),
        ];
    }

    /**
     * @return list<Stat>
     */
    protected function vendorTaskStats(): array
    {
        $open = TaskResource::getEloquentQuery()->toBase()
            ->whereIn('status', ['open', 'in_progress'])
            ->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(cost), 0) AS cost')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $year = TaskResource::getEloquentQuery()->toBase()
            ->where('status', 'completed')
            ->where('work_timestamp', '>=', now()->startOfYear())
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(cost), 0) AS cost, COUNT(DISTINCT vendor_id) AS vendors')
            ->first();

        $openCount = (int) $open->sum('total');

        return [
            Stat::make('Pekerjaan Vendor Berjalan', Rupiah::number($openCount))
                ->description(Rupiah::number((int) ($open['open']->total ?? 0)).' open · '.Rupiah::number((int) ($open['in_progress']->total ?? 0)).' in progress · '.Rupiah::compact((float) $open->sum('cost')))
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($openCount > 0 ? 'warning' : 'gray')
                ->url(TaskResource::getUrl()),
            Stat::make('Biaya Vendor Tahun Ini', Rupiah::compact((float) ($year->cost ?? 0)))
                ->description(Rupiah::number((int) ($year->total ?? 0)).' pekerjaan selesai · '.Rupiah::number((int) ($year->vendors ?? 0)).' vendor'.(VendorResource::canViewAny() ? ' dari '.Rupiah::number((int) VendorResource::getEloquentQuery()->count()).' terdaftar' : ''))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(TaskResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => 'completed']]])),
        ];
    }
}
