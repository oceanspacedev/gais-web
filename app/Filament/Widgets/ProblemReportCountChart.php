<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProblemReportResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ProblemReportCategory;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Lapor Gangguan (sumo: "Jumlah Laporan Gangguan"): laporan per pelapor
 * (10 terbanyak), bisa disaring per jenis gangguan, setahun terakhir.
 */
class ProblemReportCountChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Laporan Gangguan per Pelapor';

    protected ?string $description = '10 pelapor dengan laporan terbanyak, 12 bulan terakhir.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 6;

    public ?string $filter = 'all';

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
                'backgroundColor' => ChartPalette::DANGER,
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('reporter')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', horizontal: true);
    }
}
