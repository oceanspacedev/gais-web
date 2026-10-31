<?php

namespace App\Filament\Widgets;

use App\Enums\ProblemReportStatus;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ProblemReportCategory;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasbor > Lapor Gangguan: laporan masuk vs selesai per bulan, 12 bulan terakhir.
 */
class ProblemReportTrendChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Tren Laporan Gangguan';

    protected ?string $description = 'Laporan masuk dan laporan ditutup per bulan, 12 bulan terakhir.';

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
        $months = MonthSeries::past(12);
        $since = MonthSeries::startOfPast(12);

        $reported = $this->scoped(ProblemReportResource::getEloquentQuery())
            ->where('reported_at', '>=', $since)
            ->toBase()
            ->selectRaw(MonthSeries::sqlKey('reported_at').' AS period, COUNT(*) AS total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $closed = $this->scoped(ProblemReportResource::getEloquentQuery())
            ->where('status', ProblemReportStatus::Closed->value)
            ->where('closed_at', '>=', $since)
            ->toBase()
            ->selectRaw(MonthSeries::sqlKey('closed_at').' AS period, COUNT(*) AS total')
            ->groupBy('period')
            ->pluck('total', 'period');

        return [
            'datasets' => [
                [
                    'label' => 'Masuk',
                    'data' => MonthSeries::fill($months, $reported),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::DANGER, 0.75),
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Ditutup',
                    'data' => MonthSeries::fill($months, $closed),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::SUCCESS, 0.75),
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', legend: true);
    }

    protected function scoped(Builder $query): Builder
    {
        if (filled($this->filter) && $this->filter !== 'all') {
            $query->where('problem_report_category_id', (int) $this->filter);
        }

        return $query;
    }
}
