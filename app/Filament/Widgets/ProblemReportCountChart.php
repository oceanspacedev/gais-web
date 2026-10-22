<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReportCategory;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Beranda sumo: "Jumlah Laporan Gangguan" per pelapor (10 terbanyak), bisa
 * disaring per jenis gangguan, setahun terakhir.
 */
class ProblemReportCountChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 13;

    protected ?string $heading = 'Laporan Gangguan per Pelapor';

    // Tanpa polling 5 detik bawaan Filament: angkanya tidak perlu real-time dan querynya berat.
    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return ['all' => 'Semua jenis'] + ProblemReportCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function getData(): array
    {
        $query = ProblemReportResource::getEloquentQuery()
            ->join('users', 'users.id', '=', 'problem_reports.user_id')
            ->where('problem_reports.reported_at', '>=', now()->subYear());

        if (filled($this->filter) && $this->filter !== 'all') {
            $query->where('problem_reports.problem_report_category_id', (int) $this->filter);
        }

        $rows = $query
            ->selectRaw('users.name AS reporter, COUNT(*) AS total')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Jumlah laporan',
                'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => '#ef4444',
            ]],
            'labels' => $rows->pluck('reporter')->all(),
        ];
    }
}
