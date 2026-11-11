<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Operasional: jumlah dan biaya pekerjaan vendor per bulan pengerjaan,
 * 12 bulan terakhir (semua status kecuali yang di Sampah).
 */
class VendorTaskCostChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Pekerjaan & Biaya Vendor';

    protected ?string $description = 'Jumlah pekerjaan dan total biaya per bulan tanggal pengerjaan.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = MonthSeries::past(12);

        $rows = TaskResource::getEloquentQuery()->toBase()
            ->where('work_timestamp', '>=', MonthSeries::startOfPast(12))
            ->selectRaw(MonthSeries::sqlKey('work_timestamp').' AS period, COUNT(*) AS total, COALESCE(SUM(cost), 0) AS cost')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Pekerjaan',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): int => (int) $row->total)),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::TEAL, 0.75),
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Biaya',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): float => (float) $row->cost), integer: false),
                    'borderColor' => ChartPalette::WARNING,
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::WARNING, 0.15),
                    'tension' => 0.3,
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 1,
                ],
            ],
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::combo('int', 'rupiah');
    }
}
