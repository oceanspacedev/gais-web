<?php

namespace App\Filament\Widgets;

use App\Enums\ProblemReportStatus;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ProblemReport;
use App\Support\Dashboard\MonthSeries;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor > Lapor Gangguan: antrean terbuka, konfirmasi pelapor, penyelesaian
 * bulan ini, dan kecepatan penanganan. Mengikuti hak lihat pengguna.
 */
class ProblemReportOverviewStats extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Ringkasan Laporan Gangguan';

    protected ?string $description = 'Laporan yang bisa Anda lihat: antrean, konfirmasi pelapor, dan kecepatan penanganan.';

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $yearStart = now()->startOfYear();
        $monthStart = now()->startOfMonth();

        $open = (int) $this->reports()->where('status', ProblemReportStatus::Pending->value)->count();
        $scheduled = (int) $this->reports()->where('status', ProblemReportStatus::Pending->value)->whereNotNull('scheduled_at')->count();
        $awaitingConfirmation = (int) $this->reports()->where('status', ProblemReportStatus::Closed->value)->where('client_status', ProblemReport::CLIENT_WAITING)->count();
        $closedThisMonth = (int) $this->reports()->where('status', ProblemReportStatus::Closed->value)->where('closed_at', '>=', $monthStart)->count();
        $reportedThisMonth = (int) $this->reports()->where('reported_at', '>=', $monthStart)->count();
        $thisYear = (int) $this->reports()->where('reported_at', '>=', $yearStart)->count();
        $cancelledThisYear = (int) $this->reports()->where('reported_at', '>=', $yearStart)->where('status', ProblemReportStatus::Cancelled->value)->count();

        $resolution = $this->reports()->toBase()
            ->where('status', ProblemReportStatus::Closed->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(90))
            ->selectRaw('COUNT(*) AS total, AVG('.self::hoursBetween('reported_at', 'closed_at').') AS hours')
            ->first();

        $months = MonthSeries::past(12);
        $perMonth = $this->reports()->toBase()
            ->where('reported_at', '>=', MonthSeries::startOfPast(12))
            ->selectRaw(MonthSeries::sqlKey('reported_at').' AS period, COUNT(*) AS total')
            ->groupBy('period')
            ->pluck('total', 'period');

        return [
            Stat::make('Laporan Terbuka', Rupiah::number($open))
                ->description(Rupiah::number($scheduled).' sudah dijadwalkan · '.Rupiah::number($open - $scheduled).' belum')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($open > 0 ? 'warning' : 'gray')
                ->url(ProblemReportResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => ProblemReportStatus::Pending->value]]])),
            Stat::make('Menunggu Konfirmasi Pelapor', Rupiah::number($awaitingConfirmation))
                ->description('terkonfirmasi otomatis 24 jam setelah ditutup')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($awaitingConfirmation > 0 ? 'primary' : 'gray')
                ->url(ProblemReportResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => ProblemReportStatus::Closed->value], 'client_status' => ['value' => ProblemReport::CLIENT_WAITING]]])),
            Stat::make('Selesai Bulan Ini', Rupiah::number($closedThisMonth))
                ->description('dari '.Rupiah::number($reportedThisMonth).' laporan masuk bulan ini')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('success'),
            Stat::make('Rata-rata Penyelesaian', self::duration((float) ($resolution->hours ?? 0)))
                ->description(Rupiah::number((int) ($resolution->total ?? 0)).' laporan ditutup dalam 90 hari terakhir')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
            Stat::make('Laporan Tahun Ini', Rupiah::number($thisYear))
                ->description(Rupiah::number($cancelledThisYear).' dibatalkan ('.Rupiah::percent($cancelledThisYear, $thisYear).')')
                ->descriptionIcon(Heroicon::OutlinedInboxStack)
                ->chart(MonthSeries::fill($months, $perMonth))
                ->chartColor('danger')
                ->color('danger')
                ->url(ProblemReportResource::getUrl()),
            Stat::make('Laporan Bulan Ini', Rupiah::number($reportedThisMonth))
                ->description('masuk sejak '.$monthStart->translatedFormat('d F'))
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('gray'),
        ];
    }

    protected function reports(): Builder
    {
        return ProblemReportResource::getEloquentQuery();
    }

    public static function duration(float $hours): string
    {
        if ($hours <= 0) {
            return '-';
        }

        if ($hours < 48) {
            return Rupiah::number($hours, 1).' jam';
        }

        return Rupiah::number($hours / 24, 1).' hari';
    }

    /**
     * Selisih jam dua kolom tanggal (MySQL / SQLite).
     */
    public static function hoursBetween(string $from, string $until): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday({$until}) - julianday({$from})) * 24",
            'pgsql' => "EXTRACT(EPOCH FROM ({$until} - {$from})) / 3600",
            default => "TIMESTAMPDIFF(MINUTE, {$from}, {$until}) / 60",
        };
    }
}
