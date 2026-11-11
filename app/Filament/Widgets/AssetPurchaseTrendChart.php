<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AssetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Aset: jumlah dan nilai perolehan aset per bulan pembelian.
 */
class AssetPurchaseTrendChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Tren Pembelian Aset';

    protected ?string $description = 'Jumlah aset dan nilai perolehan per bulan tanggal pembelian.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    public ?string $filter = '12';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '12' => '12 bulan terakhir',
            '24' => '24 bulan terakhir',
            '36' => '36 bulan terakhir',
        ];
    }

    protected function getData(): array
    {
        $months = MonthSeries::past($this->monthCount());

        $rows = AssetResource::getEloquentQuery()->toBase()
            ->where('purchase_date', '>=', MonthSeries::startOfPast($this->monthCount())->toDateString())
            ->selectRaw(MonthSeries::sqlKey('purchase_date').' AS period, COUNT(*) AS total, COALESCE(SUM(item_price * qty), 0) AS value')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Jumlah aset',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): int => (int) $row->total)),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::PRIMARY, 0.7),
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Nilai perolehan',
                    'data' => MonthSeries::fill($months, $rows->map(fn (object $row): float => (float) $row->value), integer: false),
                    'borderColor' => ChartPalette::SUCCESS,
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::SUCCESS, 0.15),
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

    private function monthCount(): int
    {
        return in_array($this->filter, ['12', '24', '36'], true) ? (int) $this->filter : 12;
    }
}
