<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProblemReportResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Lapor Gangguan (sumo: "Kategori Laporan Gangguan Tertinggi"), setahun terakhir.
 */
class ProblemReportCategoryChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Jenis Gangguan Terbanyak';

    protected ?string $description = 'Porsi tiap jenis gangguan, 12 bulan terakhir.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 6;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = ProblemReportResource::getEloquentQuery()
            ->leftJoin('problem_report_categories', 'problem_report_categories.id', '=', 'problem_reports.problem_report_category_id')
            ->where('problem_reports.reported_at', '>=', now()->subYear())
            ->selectRaw("COALESCE(problem_report_categories.name, 'Tanpa jenis') AS category, COUNT(*) AS total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Jumlah laporan',
                'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => ChartPalette::categoricalFor($rows->count()),
                'borderWidth' => 0,
            ]],
            'labels' => $rows->pluck('category')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::doughnut();
    }
}
