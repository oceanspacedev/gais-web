<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\VehicleChecksheetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Operasional: perjalanan kendaraan dan jarak tempuh per bulan, 12 bulan terakhir.
 */
class VehicleUsageChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Pemakaian Kendaraan';

    protected ?string $description = 'Jumlah perjalanan (checksheet) dan total jarak tempuh per bulan keberangkatan.';

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

        $rows = VehicleChecksheetResource::getEloquentQuery()->toBase()
            ->where('departure_time', '>=', MonthSeries::startOfPast(12))
            ->selectRaw(MonthSeries::sqlKey('departure_time').' AS period, COUNT(*) AS trips, COALESCE(SUM(distance_traveled), 0) AS km')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Perjalanan',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): int => (int) $row->trips)),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::INFO, 0.75),
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Jarak tempuh',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): float => (float) $row->km), integer: false),
                    'borderColor' => ChartPalette::VIOLET,
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::VIOLET, 0.15),
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
        return ChartOptions::combo('int', 'km');
    }
}
