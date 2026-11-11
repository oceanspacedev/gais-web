<?php

namespace App\Filament\Widgets;

use App\Enums\AssetServiceStatus;
use App\Enums\ItemRequestClientStatus;
use App\Enums\NbhStatus;
use App\Enums\ProblemReportStatus;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetServiceResource;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Resources\RentResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\VehicleChecksheetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ObChecksheet;
use App\Services\ItemRequestWorkflow;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dasbor: antrean lintas modul yang menunggu keputusan atau tindak lanjut.
 * Tiap kartu hanya tampil bila menunya boleh dibuka, dan angkanya mengikuti
 * hak lihat pengguna (pemohon melihat antrean miliknya sendiri).
 */
class AttentionStats extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    // Tampil langsung saat halaman dimuat (bukan lazy): kartu terpenting, querynya hanya beberapa COUNT.
    protected static bool $isLazy = false;

    protected ?string $heading = 'Perlu Tindakan';

    protected ?string $description = 'Antrean yang menunggu keputusan atau tindak lanjut hari ini. Klik kartu untuk membuka daftarnya.';

    protected ?string $pollingInterval = null;

    protected static function hasAnythingToShow(): bool
    {
        return collect(self::resources())->contains(fn (string $resource): bool => $resource::canViewAny());
    }

    /**
     * @return list<class-string<\Filament\Resources\Resource>>
     */
    private static function resources(): array
    {
        return [
            ItemRequestResource::class, ProblemReportResource::class, AssetResource::class, AssetServiceResource::class,
            TaskResource::class, ObChecksheetResource::class, VehicleChecksheetResource::class, InsuranceResource::class, RentResource::class,
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $stats = [];

        if (ItemRequestResource::canViewAny()) {
            $awaitingExecutor = (int) app(ItemRequestWorkflow::class)->scopeAwaitingExecutor(ItemRequestResource::getEloquentQuery())->count();
            $awaitingRequester = (int) ItemRequestResource::getEloquentQuery()->where('client_status', ItemRequestClientStatus::Closed->value)->count();

            $stats[] = self::stat('Pengajuan Menunggu Executor', $awaitingExecutor, 'belum diproses executor', Heroicon::OutlinedShoppingCart, 'warning',
                ItemRequestResource::getUrl(parameters: ['tableFilters' => ['awaiting_executor' => ['isActive' => true]]]));
            $stats[] = self::stat('Pengajuan Menunggu Konfirmasi', $awaitingRequester, 'selesai, menunggu konfirmasi pemohon', Heroicon::OutlinedCheckCircle, 'primary',
                ItemRequestResource::getUrl(parameters: ['tableFilters' => ['client_status' => ['value' => ItemRequestClientStatus::Closed->value]]]));
        }

        if (ProblemReportResource::canViewAny()) {
            $open = (int) ProblemReportResource::getEloquentQuery()->where('status', ProblemReportStatus::Pending->value)->count();

            $stats[] = self::stat('Gangguan Terbuka', $open, 'laporan belum ditutup', Heroicon::OutlinedExclamationTriangle, 'danger',
                ProblemReportResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => ProblemReportStatus::Pending->value]]]));
        }

        if (AssetResource::canViewAny()) {
            $nbh = (int) AssetResource::getEloquentQuery()->where('nbh_status', NbhStatus::Pending->value)->count();

            $stats[] = self::stat('Aset Menunggu NBH', $nbh, 'rusak / hilang tanpa berita acara', Heroicon::OutlinedCube, 'danger',
                AssetResource::getUrl(parameters: ['tableFilters' => ['nbh_status' => ['values' => [NbhStatus::Pending->value]]]]));
        }

        if (AssetServiceResource::canViewAny()) {
            $services = (int) AssetServiceResource::getEloquentQuery()->whereIn('status', [AssetServiceStatus::Pending->value, AssetServiceStatus::InProgress->value])->count();

            $stats[] = self::stat('Servis Aset Berjalan', $services, 'menunggu atau sedang diservis', Heroicon::OutlinedWrenchScrewdriver, 'info', AssetServiceResource::getUrl());
        }

        if (TaskResource::canViewAny()) {
            $tasks = (int) TaskResource::getEloquentQuery()->whereIn('status', ['open', 'in_progress'])->count();

            $stats[] = self::stat('Pekerjaan Vendor Berjalan', $tasks, 'open atau in progress', Heroicon::OutlinedBriefcase, 'warning', TaskResource::getUrl());
        }

        if (ObChecksheetResource::canViewAny()) {
            $pendingToday = (int) ObChecksheetResource::getEloquentQuery()->whereDate('scheduled_date', today())->where('status', '<>', ObChecksheet::STATUS_COMPLETED)->count();

            $stats[] = self::stat('OB Belum Selesai Hari Ini', $pendingToday, 'tugas kebersihan hari ini', Heroicon::OutlinedSparkles, 'warning',
                ObChecksheetResource::getUrl(parameters: ['tableFilters' => ['scheduled_date' => ['from' => today()->toDateString(), 'until' => today()->toDateString()]]]));
        }

        if (VehicleChecksheetResource::canViewAny()) {
            $out = (int) VehicleChecksheetResource::getEloquentQuery()->whereNull('return_time')->count();

            $stats[] = self::stat('Kendaraan Belum Kembali', $out, 'checksheet tanpa waktu kembali', Heroicon::OutlinedTruck, 'danger', VehicleChecksheetResource::getUrl());
        }

        if (InsuranceResource::canViewAny()) {
            $due = (int) InsuranceResource::getEloquentQuery()->activeContracts()->dueWithin(30)->count();

            $stats[] = self::stat('Polis Jatuh Tempo ≤ 30 Hari', $due, 'termasuk yang sudah lewat', Heroicon::OutlinedShieldCheck, 'danger',
                InsuranceResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]]));
        }

        if (RentResource::canViewAny()) {
            $due = (int) RentResource::getEloquentQuery()->activeContracts()->dueWithin(30)->count();

            $stats[] = self::stat('Sewa Berakhir ≤ 30 Hari', $due, 'termasuk yang sudah lewat', Heroicon::OutlinedBuildingStorefront, 'danger',
                RentResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]]));
        }

        return $stats;
    }

    private static function stat(string $label, int $count, string $description, Heroicon $icon, string $color, string $url): Stat
    {
        return Stat::make($label, Rupiah::number($count))
            ->description($count === 0 ? 'tidak ada antrean' : $description)
            ->descriptionIcon($icon)
            ->color($count === 0 ? 'gray' : $color)
            ->url($url);
    }
}
